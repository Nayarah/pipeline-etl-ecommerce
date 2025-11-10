<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(900);
require_once 'config.php';

// --- CONFIGURAÇÃO ---
$batch_size = 50; // Aumentamos o lote pois o script será mais rápido
// --------------------

echo "================================================\n";
echo "INICIANDO WORKER DE ESTOQUE (v2 - Lógica de Mapa) - " . date('Y-m-d H:i:s') . "\n";

// 1. Carrega o mapa de anúncios para a memória para consultas rápidas
$mapa_result = $mysqli->query("SELECT sku, id_anuncio_pai, logistic_type FROM mapa_produtos_anuncios");
$mapa_por_sku = [];
while ($linha = $mapa_result->fetch_assoc()) {
    $mapa_por_sku[$linha['sku']][] = $linha; // Agrupa por SKU, pois um SKU pode ter vários anúncios
}
$mapa_result->free();

// 2. Trava um lote de tarefas de estoque para processar
$mysqli->query("UPDATE tarefas_pendentes_estoque SET status = 'processando', data_processamento = NOW() WHERE status = 'pendente' LIMIT $batch_size");
$resultado = $mysqli->query("SELECT id, sku, id_produto_tiny, data_snapshot FROM tarefas_pendentes_estoque WHERE status = 'processando'");
$tarefas = $resultado->fetch_all(MYSQLI_ASSOC);
$resultado->free();

if (empty($tarefas)) { die("Nenhuma tarefa de estoque pendente encontrada. Encerrando.\n"); }
echo count($tarefas) . " tarefas travadas para processamento.\n";

$token = obterTokenMeli();
if (!$token) { die("ERRO CRÍTICO: Não foi possível obter um token de acesso válido."); }

$stmt_insert = $mysqli->prepare("
    INSERT INTO estoque_diario (sku, data_snapshot, estoque_geral_tiny, estoque_direct_tiny, estoque_full_ml) 
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE estoque_geral_tiny = VALUES(estoque_geral_tiny), estoque_direct_tiny = VALUES(estoque_direct_tiny), estoque_full_ml = VALUES(estoque_full_ml)
");

// --- Início da Etapa de Processamento do Lote ---

foreach ($tarefas as $tarefa) {
    $id_tarefa = $tarefa['id'];
    $sku_tarefa = $tarefa['sku'];
    $data_snapshot = $tarefa['data_snapshot'];
    echo "<hr>Processando SKU: <b>$sku_tarefa</b> para a data $data_snapshot...<br>";


 // --- Busca de Estoque no Tiny (Lógica Corrigida com Depósito 'Direct') ---
    $estoque_geral_tiny = 0;
    $estoque_direct_tiny = 0; // Nova variável para o depósito Direct

    // A lógica de duas chamadas que validamos está correta.
    // Etapa A: Pesquisar pelo SKU para obter o ID interno do Tiny
    $postDataPesquisa = http_build_query(['token' => TINY_API_TOKEN, 'formato' => 'json', 'pesquisa' => $sku_tarefa]);
    $responsePesquisa = fazerRequisicaoCurl('https://api.tiny.com.br/api2/produtos.pesquisa.php', $postDataPesquisa);
    $dataPesquisa = json_decode($responsePesquisa, true);

    // Verifica se a PESQUISA encontrou algum produto
    if (isset($dataPesquisa['retorno']['status']) && $dataPesquisa['retorno']['status'] == 'OK' && !empty($dataPesquisa['retorno']['produtos'])) {
        $produto_id_tiny = $dataPesquisa['retorno']['produtos'][0]['produto']['id'];

        // Etapa B: Usar o ID interno para OBTER o estoque detalhado
        $postDataEstoque = http_build_query(['token' => TINY_API_TOKEN, 'formato' => 'json', 'id' => $produto_id_tiny]);
        $responseEstoque = fazerRequisicaoCurl('https://api.tiny.com.br/api2/produto.obter.estoque.php', $postDataEstoque);
        $dataEstoque = json_decode($responseEstoque, true);

        // Agora sim, verifica a resposta da chamada de ESTOQUE
        if (isset($dataEstoque['retorno']['status']) && $dataEstoque['retorno']['status'] == 'OK' && isset($dataEstoque['retorno']['produto']['depositos'])) {
            // Loop nos depósitos para encontrar 'Geral' e 'Direct'
            foreach ($dataEstoque['retorno']['produto']['depositos'] as $deposito_item) {
                if (isset($deposito_item['deposito']['nome'])) {
                    if ($deposito_item['deposito']['nome'] == 'Geral') {
                        $estoque_geral_tiny = (int)$deposito_item['deposito']['saldo'];
                    }
                    if ($deposito_item['deposito']['nome'] == 'Direct') {
                        $estoque_direct_tiny = (int)$deposito_item['deposito']['saldo'];
                    }
                }
            }
        }
    } else {
        echo "AVISO: SKU '$sku_tarefa' não foi encontrado no Tiny na etapa de pesquisa.<br>";
    }
    
    // Echo de debug aprimorado para mostrar os dois estoques
    echo "Estoque Tiny encontrado: Geral(<b>$estoque_geral_tiny</b>) Direct(<b>$estoque_direct_tiny</b>) <br>";
    sleep(1);

    // ==================================================================
    // #### INÍCIO DA LÓGICA DE ESTOQUE FULL (VALIDADA PELO SEU TESTE) ####
    // ==================================================================
    $estoque_full = 0;
    $id_anuncio_full = 'N/A';
    
    // 1. Verifica se o SKU da tarefa existe no nosso mapa local
    if (isset($mapa_por_sku[$sku_tarefa])) {
        // Percorre todos os anúncios associados a este SKU
        foreach ($mapa_por_sku[$sku_tarefa] as $anuncio_mapeado) {
            
            // 2. Verifica se o anúncio é do tipo 'fulfillment'
            if (isset($anuncio_mapeado['logistic_type']) && $anuncio_mapeado['logistic_type'] == 'fulfillment') {
                $id_anuncio_pai = $anuncio_mapeado['id_anuncio_pai'];
                
                // 3. Busca os detalhes completos do anúncio pai
                $url_item = "https://api.mercadolibre.com/items/$id_anuncio_pai?include_attributes=all";
                $response_item = fazerRequisicaoMeli($url_item, $token);
                sleep(1);
                
                if ($response_item['http_code'] == 200) {
                    $anuncio = $response_item['body'];
                    $inventory_id = null;

                    // 4. Procura o SKU na variação correta para obter o inventory_id
                    if (!empty($anuncio['variations'])) {
                        foreach ($anuncio['variations'] as $variation) {
                            $sku_da_variacao = null;
                            if (!empty($variation['attributes'])) {
                               foreach($variation['attributes'] as $attr) {
                                   if(isset($attr['id']) && $attr['id'] == 'SELLER_SKU') {
                                       $sku_da_variacao = $attr['value_name'];
                                       break;
                                   }
                               }
                            }
                            if (empty($sku_da_variacao) && !empty($variation['seller_custom_field'])) {
                                $sku_da_variacao = $variation['seller_custom_field'];
                            }
                            if ($sku_da_variacao == $sku_tarefa) {
                                $inventory_id = $variation['inventory_id'] ?? null;
                                break;
                            }
                        }
                    } else { // Se não tem variação, pega o inventory_id do anúncio pai
                       $sku_do_anuncio = $anuncio['seller_custom_field'] ?? null;
                       if (isset($anuncio['attributes'])) {
                           foreach($anuncio['attributes'] as $attr) {
                               if(isset($attr['id']) && $attr['id'] == 'SELLER_SKU') { $sku_do_anuncio = $attr['value_name']; break; }
                           }
                       }
                       if ($sku_do_anuncio == $sku_tarefa) {
                           $inventory_id = $anuncio['inventory_id'] ?? null;
                       }
                    }

                    // 5. Se encontrou um inventory_id, faz a chamada final para o estoque detalhado
                    if (!empty($inventory_id)) {
                        $url_estoque_full = "https://api.mercadolibre.com/inventories/$inventory_id/stock/fulfillment";
                        $response_estoque_full = fazerRequisicaoMeli($url_estoque_full, $token);
                        
                        if(isset($response_estoque_full['body']['total'])) {
                            $estoque_full = (int)$response_estoque_full['body']['total'];
                            $id_anuncio_full = $id_anuncio_pai;
                        }
                    }
                }
                // Como já encontramos o estoque de um anúncio Full, paramos a busca
                break; 
            }
        }
    }

    echo "<b>Estoque Full final para o SKU $sku_tarefa: $estoque_full</b> (Anúncio ID: $id_anuncio_full)<br>";

    $stmt_insert->bind_param("ssiii", $sku_tarefa, $data_snapshot, $estoque_geral_tiny, $estoque_direct_tiny, $estoque_full);
    $stmt_insert->execute();

    $mysqli->query("UPDATE tarefas_pendentes_estoque SET status = 'concluido' WHERE id = " . $id_tarefa);
}
$stmt_insert->close();
$mysqli->close();
echo "<hr>Lote de tarefas de estoque processado com sucesso.\n";
?>