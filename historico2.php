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
$resultado = $mysqli->query("SELECT id, sku, data_snapshot FROM tarefas_pendentes_estoque WHERE status = 'processando'");
$tarefas = $resultado->fetch_all(MYSQLI_ASSOC);
$resultado->free();

if (empty($tarefas)) { die("Nenhuma tarefa de estoque pendente encontrada. Encerrando.\n"); }
echo count($tarefas) . " tarefas travadas para processamento.\n";

$token = obterTokenMeli();
if (!$token) { die("ERRO CRÍTICO: Não foi possível obter um token de acesso válido."); }

$stmt_insert = $mysqli->prepare("
    INSERT INTO estoque_diario (sku, data_snapshot, estoque_geral_tiny, estoque_full_ml) 
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE estoque_geral_tiny = VALUES(estoque_geral_tiny), estoque_full_ml = VALUES(estoque_full_ml)
");

// --- Início da Etapa de Processamento do Lote ---

foreach ($tarefas as $tarefa) {
    $id_tarefa = $tarefa['id'];
    $sku_tarefa = $tarefa['sku'];
    $data_snapshot = $tarefa['data_snapshot'];
    echo "<hr>Processando SKU: <b>$sku_tarefa</b> para a data $data_snapshot...<br>";

    // --- Busca de Estoque no Tiny ---
    $estoque_tiny = 0;
    // ... (seu código de busca no Tiny, que já está funcionando, permanece aqui) ...
    echo "Estoque Tiny encontrado: $estoque_tiny <br>";
    sleep(1);

    // ==================================================================
    // #### INÍCIO DA LÓGICA DE ESTOQUE FULL (USANDO SUA LÓGICA VALIDADA) ####
    // ==================================================================

    $estoque_full = 0;
    $id_anuncio_full = 'N/A';
    
    // 1. Usa o SKU da tarefa para encontrar o(s) anúncio(s) pai no mapa
    if (isset($mapa_por_sku[$sku_tarefa])) {
        foreach ($mapa_por_sku[$sku_tarefa] as $anuncio_mapeado) {
            
            // 2. Verifica se o anúncio é do tipo 'fulfillment'
            if (isset($anuncio_mapeado['logistic_type']) && $anuncio_mapeado['logistic_type'] == 'fulfillment') {
                $id_anuncio_pai = $anuncio_mapeado['id_anuncio_pai'];
                echo "-> Encontrado anúncio Full no mapa (ID: $id_anuncio_pai). Buscando detalhes...<br>";
                
                // 3. Busca os detalhes completos do ANÚNCIO PAI específico
                $url_item = "https://api.mercadolibre.com/items/$id_anuncio_pai?include_attributes=all";
                $response_item = fazerRequisicaoMeli($url_item, $token);
                sleep(1);
                
                if ($response_item['http_code'] == 200) {
                    $anuncio = $response_item['body'];
                    $estoque_encontrado_nesta_chamada = false;

                    // 4. Lógica para ANÚNCIOS COM VARIAÇÕES
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
                                $estoque_full = (int)($variation['available_quantity'] ?? 0);
                                $id_anuncio_full = $id_anuncio_pai;
                                $estoque_encontrado_nesta_chamada = true;
                                break;
                            }
                        }
                    } 
                    
                    // 5. Lógica para ANÚNCIOS SIMPLES (se não encontrou nas variações)
                    if (!$estoque_encontrado_nesta_chamada) {
                        $sku_do_anuncio = null;
                        if (!empty($anuncio['attributes'])) {
                           foreach($anuncio['attributes'] as $attr) {
                               if(isset($attr['id']) && $attr['id'] == 'SELLER_SKU') {
                                   $sku_do_anuncio = $attr['value_name'];
                                   break;
                               }
                           }
                        }
                        if (empty($sku_do_anuncio) && !empty($anuncio['seller_custom_field'])) {
                          $sku_do_anuncio = $anuncio['seller_custom_field'];
                        }

                       if ($sku_do_anuncio == $sku_tarefa) {
                            $estoque_full = (int)($anuncio['available_quantity'] ?? 0);
                            $id_anuncio_full = $id_anuncio_pai;
                       }
                    }
                }
                // Como já encontramos o estoque de um anúncio Full, paramos a busca
                break; 
            }
        }
    } else {
        echo "AVISO: SKU '$sku_tarefa' não foi encontrado no mapa de produtos/anúncios.<br>";
    }

    echo "<b>Estoque Full final para o SKU $sku_tarefa: $estoque_full</b> (Encontrado no Anúncio ID: $id_anuncio_full)<br>";

    $stmt_insert->bind_param("ssii", $sku_tarefa, $data_snapshot, $estoque_tiny, $estoque_full);
    $stmt_insert->execute();

    $mysqli->query("UPDATE tarefas_pendentes_estoque SET status = 'concluido' WHERE id = " . $id_tarefa);
}

$stmt_insert->close();
$mysqli->close();
echo "<hr>Lote de tarefas de estoque processado com sucesso.\n";
?>