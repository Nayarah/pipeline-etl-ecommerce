<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(900); // 15 minutos

// ==================================================================
// --- CONFIGURAÇÃO DA TAREFA DE BACKFILL ---
// ==================================================================
$limite_de_tempo_segundos = 840; // 14 minutos, para segurança
$tempo_inicial = microtime(true);

require_once 'config.php';

$arquivo_de_estado = 'data_backfill_lotes_atual.txt';
$data_inicio_geral = '2025-01-01';
$data_fim_geral = date('Y-m-d', strtotime('-4 day'));

$data_inicio_estoque = '2025-07-01';
// ==================================================================


// #### CORREÇÃO: A FUNÇÃO FOI MOVIDA PARA CÁ ####
// Uma função só precisa ser declarada uma vez, fora de qualquer laço.
function agregar_dados_venda($vendas_do_anuncio, $catalogo) {
    $agregados = [
        'vendas_totais_qtd' => 0, 'faturamento_total' => 0.0, 'tarifa_venda_total' => 0.0,
        'custo_frete_total' => 0.0, 'liquido_recebido_total' => 0.0, 'custo_produto_total' => 0.0
    ];
    if (!empty($vendas_do_anuncio)) {
        foreach ($vendas_do_anuncio as $venda) {
            $qtd = (int)$venda['qtd_vendida'];
            $agregados['vendas_totais_qtd'] += $qtd;
            $agregados['faturamento_total'] += (float)$venda['faturamento_bruto_item'];
            $agregados['tarifa_venda_total'] += (float)$venda['tarifa_ml'];
            $agregados['custo_frete_total'] += (float)$venda['custo_frete_rateado'];
            $agregados['liquido_recebido_total'] += (float)$venda['liquido_recebido'];
            $sku_da_venda = $venda['sku'] ?? null;
            if ($sku_da_venda && isset($catalogo[$sku_da_venda])) {
                $custo_unitario = (float)($catalogo[$sku_da_venda]['custo_produto'] ?? 0.00);
                $agregados['custo_produto_total'] += $custo_unitario * $qtd;
            }
        }
    }
    return $agregados;
}


echo "================================================\n";
echo "INICIANDO TRABALHADOR DE BACKFILL EM LOTES\n";
echo "Data/Hora de Início: " . date('Y-m-d H:i:s') . "\n";
echo "Limite de tempo para esta execução: $limite_de_tempo_segundos segundos.\n";

if (!file_exists($arquivo_de_estado)) {
    die("ERRO: O arquivo de estado '$arquivo_de_estado' não foi encontrado. Crie-o com a data de início (ex: $data_inicio_geral).");
}
$data_alvo = new DateTime(trim(file_get_contents($arquivo_de_estado)));

// Laço principal que processa vários dias
while (true) {
    $data_fim = new DateTime($data_fim_geral);
    if ($data_alvo > $data_fim) {
        echo "✅ PROCESSO DE BACKFILL CONCLUÍDO.\n";
        die();
    }

    $data_alvo_str = $data_alvo->format('Y-m-d');
    echo "\n--- Processando data: $data_alvo_str ---\n";

    // --- INÍCIO DO PROCESSAMENTO DE UM DIA ---
    $catalogo = []; $mapa_anuncios = []; $dados_trafego = []; $dados_vendas = []; $dados_estoque = [];
    $resultado_catalogo = $mysqli->query("SELECT sku, nome_produto, custo_produto FROM produtos_catalogo");
    while ($linha = $resultado_catalogo->fetch_assoc()) { $catalogo[$linha['sku']] = $linha; }
    $resultado_catalogo->free();
    $resultado_mapa = $mysqli->query("SELECT id_anuncio_canal, sku_produto, id_anuncio_pai, titulo_anuncio, categoria_anuncio FROM anuncios_canais");
    while ($linha = $resultado_mapa->fetch_assoc()) { $mapa_anuncios[$linha['id_anuncio_canal']] = $linha; if (!isset($mapa_anuncios[$linha['id_anuncio_pai']])) { $mapa_anuncios[$linha['id_anuncio_pai']] = $linha; } }
    $resultado_mapa->free();
    $stmt_trafego = $mysqli->prepare("SELECT * FROM trafego_diario WHERE data_metrica = ?"); $stmt_trafego->bind_param("s", $data_alvo_str); $stmt_trafego->execute();
    $resultado_trafego = $stmt_trafego->get_result(); while ($linha = $resultado_trafego->fetch_assoc()) { $dados_trafego[$linha['id_anuncio']] = $linha; } $stmt_trafego->close();
    echo "-> Tráfego: " . count($dados_trafego) . ". ";
    $stmt_vendas = $mysqli->prepare("SELECT * FROM vendas_financeiro WHERE DATE(data_venda) = ?"); $stmt_vendas->bind_param("s", $data_alvo_str); $stmt_vendas->execute();
    $resultado_vendas = $stmt_vendas->get_result(); $num_vendas = $resultado_vendas->num_rows; while ($linha = $resultado_vendas->fetch_assoc()) { $dados_vendas[$linha['id_anuncio']][] = $linha; } $stmt_vendas->close();
    echo "Vendas: " . $num_vendas . ". ";
    $data_inicio_estoque_obj = new DateTime($data_inicio_estoque);
    if ($data_alvo >= $data_inicio_estoque_obj) {
        $stmt_estoque = $mysqli->prepare("SELECT sku, estoque_geral_tiny, estoque_full_ml FROM estoque_diario WHERE data_snapshot = ?"); $stmt_estoque->bind_param("s", $data_alvo_str); $stmt_estoque->execute();
        $resultado_estoque = $stmt_estoque->get_result(); while ($linha = $resultado_estoque->fetch_assoc()) { $dados_estoque[$linha['sku']] = $linha; } $stmt_estoque->close();
        echo "Estoque: " . count($dados_estoque) . ".\n";
    } else { echo "Estoque: N/A (fora do período).\n"; }
    $stmt_insert = $mysqli->prepare("INSERT INTO relatorio_diario (data_relatorio, id_anuncio, sku, titulo_anuncio, categoria_anuncio, impressoes_ads, cliques_ads, custo_ads, vendas_ads_qtd, faturamento_ads, vendas_organicas_qtd, visitas_totais, visitas_organicas_reais, vendas_totais_qtd, faturamento_total, tarifa_venda_total, custo_frete_total, liquido_recebido_total, custo_produto_total, estoque_full_ml, estoque_geral_tiny) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE sku=VALUES(sku), titulo_anuncio=VALUES(titulo_anuncio), categoria_anuncio=VALUES(categoria_anuncio), impressoes_ads=VALUES(impressoes_ads), cliques_ads=VALUES(cliques_ads), custo_ads=VALUES(custo_ads), vendas_ads_qtd=VALUES(vendas_ads_qtd), faturamento_ads=VALUES(faturamento_ads), vendas_organicas_qtd=VALUES(vendas_organicas_qtd), visitas_totais=VALUES(visitas_totais), visitas_organicas_reais=VALUES(visitas_organicas_reais), vendas_totais_qtd=VALUES(vendas_totais_qtd), faturamento_total=VALUES(faturamento_total), tarifa_venda_total=VALUES(tarifa_venda_total), custo_frete_total=VALUES(custo_frete_total), liquido_recebido_total=VALUES(liquido_recebido_total), custo_produto_total=VALUES(custo_produto_total), estoque_full_ml=VALUES(estoque_full_ml), estoque_geral_tiny=VALUES(estoque_geral_tiny)");
    $anuncios_processados = [];
    foreach ($dados_trafego as $id_anuncio_pai => $trafego) { $anuncios_processados[$id_anuncio_pai] = true; $info_canal = $mapa_anuncios[$id_anuncio_pai] ?? null; $sku_final = $info_canal['sku_produto'] ?? 'N/A'; $titulo = $info_canal['titulo_anuncio'] ?? 'N/A'; $categoria = $info_canal['categoria_anuncio'] ?? 'N/A'; $vendas_deste_anuncio = $dados_vendas[$id_anuncio_pai] ?? []; $dados_financeiros_agg = agregar_dados_venda($vendas_deste_anuncio, $catalogo); $impressoes_ads = (int)($trafego['impressoes_ads'] ?? 0); $cliques_ads = (int)($trafego['cliques_ads'] ?? 0); $custo_ads = (float)($trafego['custo_ads'] ?? 0.00); $vendas_ads_qtd = (int)($trafego['vendas_ads_qtd'] ?? 0); $faturamento_ads = (float)($trafego['faturamento_ads'] ?? 0.00); $visitas_totais = (int)($trafego['visitas_totais'] ?? 0); $visitas_organicas_reais = $visitas_totais - $cliques_ads; $estoque = $dados_estoque[$sku_final] ?? []; $estoque_full_ml = (int)($estoque['estoque_full_ml'] ?? 0); $estoque_geral_tiny = (int)($estoque['estoque_geral_tiny'] ?? 0); $stmt_insert->bind_param("sssssiidiidiiidddddii", $data_alvo_str, $id_anuncio_pai, $sku_final, $titulo, $categoria, $impressoes_ads, $cliques_ads, $custo_ads, $vendas_ads_qtd, $faturamento_ads, $dados_financeiros_agg['vendas_totais_qtd'], $visitas_totais, $visitas_organicas_reais, $dados_financeiros_agg['vendas_totais_qtd'], $dados_financeiros_agg['faturamento_total'], $dados_financeiros_agg['tarifa_venda_total'], $dados_financeiros_agg['custo_frete_total'], $dados_financeiros_agg['liquido_recebido_total'], $dados_financeiros_agg['custo_produto_total'], $estoque_full_ml, $estoque_geral_tiny); $stmt_insert->execute(); }
    foreach ($dados_vendas as $id_anuncio_pai => $vendas) { if (isset($anuncios_processados[$id_anuncio_pai])) continue; $info_canal = $mapa_anuncios[$id_anuncio_pai] ?? null; $sku_final = $info_canal['sku_produto'] ?? 'N/A'; $titulo = $info_canal['titulo_anuncio'] ?? 'N/A'; $categoria = $info_canal['categoria_anuncio'] ?? 'N/A'; $dados_financeiros_agg = agregar_dados_venda($vendas, $catalogo); $zero_i = 0; $zero_d = 0.0; $estoque = $dados_estoque[$sku_final] ?? []; $estoque_full_ml = (int)($estoque['estoque_full_ml'] ?? 0); $estoque_geral_tiny = (int)($estoque['estoque_geral_tiny'] ?? 0); $stmt_insert->bind_param("sssssiidiidiiidddddii", $data_alvo_str, $id_anuncio_pai, $sku_final, $titulo, $categoria, $zero_i, $zero_i, $zero_d, $zero_i, $zero_d, $dados_financeiros_agg['vendas_totais_qtd'], $zero_i, $zero_i, $dados_financeiros_agg['vendas_totais_qtd'], $dados_financeiros_agg['faturamento_total'], $dados_financeiros_agg['tarifa_venda_total'], $dados_financeiros_agg['custo_frete_total'], $dados_financeiros_agg['liquido_recebido_total'], $dados_financeiros_agg['custo_produto_total'], $estoque_full_ml, $estoque_geral_tiny); $stmt_insert->execute(); }
    $stmt_insert->close();
    // --- FIM DO PROCESSAMENTO DE UM DIA ---

    // ATUALIZA A DATA PARA O PRÓXIMO DIA
    $data_alvo->modify('+1 day');
    file_put_contents($arquivo_de_estado, $data_alvo->format('Y-m-d'));
    
    // VERIFICA O TEMPO DE EXECUÇÃO
    $tempo_atual = microtime(true);
    $tempo_decorrido = $tempo_atual - $tempo_inicial;
    
    echo "Dia $data_alvo_str concluído. Tempo decorrido: " . round($tempo_decorrido) . " segundos.\n";
    
    if ($tempo_decorrido >= $limite_de_tempo_segundos) {
        echo "--> Limite de tempo de segurança atingido. A tarefa será encerrada e continuará do dia " . $data_alvo->format('Y-m-d') . " na próxima execução.\n";
        break; // Sai do loop while
    }
}

$mysqli->close();
echo "================================================\n";
?>