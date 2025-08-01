<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(900); // 15 minutos
require_once 'config.php';

echo "Iniciando tarefa de sincronização do catálogo de produtos com o TinyERP (v3 - Lógica Final de SKU Pai)...\n<br>";

// === ETAPA 1: CRIAR O MAPA DE ID para SKU ===
echo "<b>Etapa 1: Criando mapa de todos os produtos do Tiny...</b><br>";
$mapa_id_para_sku = [];
$pagina_mapa = 1;
$hasMorePagesMap = true;
$token = TINY_API_TOKEN;

while($hasMorePagesMap) {
    $postDataMapa = http_build_query(['token' => $token, 'formato' => 'json', 'pagina' => $pagina_mapa]);
    $responseMapa = fazerRequisicaoCurl('https://api.tiny.com.br/api2/produtos.pesquisa.php', $postDataMapa);
    $dataMapa = json_decode($responseMapa, true);

    if (isset($dataMapa['retorno']['status']) && $dataMapa['retorno']['status'] == 'OK' && !empty($dataMapa['retorno']['produtos'])) {
        foreach ($dataMapa['retorno']['produtos'] as $item) {
            $produto = $item['produto'];
            if (isset($produto['id']) && isset($produto['codigo'])) {
                $mapa_id_para_sku[$produto['id']] = $produto['codigo'];
            }
        }
        $pagina_mapa++;
    } else {
        $hasMorePagesMap = false;
    }
    sleep(1);
}
echo "Mapa criado com " . count($mapa_id_para_sku) . " produtos.<br><hr>";


// === ETAPA 2: SINCRONIZAR PRODUTOS USANDO O MAPA ===
echo "<b>Etapa 2: Sincronizando produtos e variações...</b><br>";
$stmt = $mysqli->prepare("
    INSERT INTO produtos_catalogo (sku, nome_produto, tipo_produto, sku_pai, custo_produto)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    nome_produto = VALUES(nome_produto),
    tipo_produto = VALUES(tipo_produto),
    sku_pai = VALUES(sku_pai),
    custo_produto = VALUES(custo_produto)
");

$pagina_atual = 1;
$hasMorePages = true;
$produtos_processados = 0;

while($hasMorePages) {
    echo "Buscando página $pagina_atual de produtos para processar...\n<br>";
    $postDataPesquisa = http_build_query(['token' => $token, 'formato' => 'json', 'pagina' => $pagina_atual]);
    $responsePesquisa = fazerRequisicaoCurl('https://api.tiny.com.br/api2/produtos.pesquisa.php', $postDataPesquisa);
    $dataPesquisa = json_decode($responsePesquisa, true);

    if (isset($dataPesquisa['retorno']['status']) && $dataPesquisa['retorno']['status'] == 'OK' && !empty($dataPesquisa['retorno']['produtos'])) {
        foreach ($dataPesquisa['retorno']['produtos'] as $item) {
            $produto = $item['produto'];
            $id_produto_tiny = $produto['id'];

            $postDataObter = http_build_query(['token' => $token, 'formato' => 'json', 'id' => $id_produto_tiny]);
            $responseObter = fazerRequisicaoCurl('https://api.tiny.com.br/api2/produto.obter.php', $postDataObter);
            $dataObter = json_decode($responseObter, true);
            
            if(isset($dataObter['retorno']['produto'])){
                $produto_completo = $dataObter['retorno']['produto'];
                
                $sku = $produto_completo['codigo'] ?? null;
                if (!$sku) continue;

                $nome = $produto_completo['nome'] ?? '';
                $tipo = $produto_completo['classe_produto'] ?? 'S'; 
                $id_pai = $produto_completo['idProdutoPai'] ?? 0;
                
                // USA O MAPA PARA TRADUZIR O ID DO PAI PARA O SKU DO PAI
                $sku_pai = ($id_pai != 0 && isset($mapa_id_para_sku[$id_pai])) ? $mapa_id_para_sku[$id_pai] : null;

                $custo = (float)str_replace(',', '.', $produto_completo['preco_custo'] ?? 0.00);

                echo "Processando SKU: $sku | Tipo: $tipo | SKU Pai: $sku_pai<br>";

                $stmt->bind_param("sssds", $sku, $nome, $tipo, $sku_pai, $custo);
                $stmt->execute();
                $produtos_processados++;
            }
             sleep(1);
        }
        $pagina_atual++;
    } else {
        $hasMorePages = false;
    }
}

$stmt->close();
$mysqli->close();
echo "<hr>✅ Tarefa concluída. Total de $produtos_processados produtos foram sincronizados.";
?>