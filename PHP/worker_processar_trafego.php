<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(900); // 15 minutos
require_once 'config.php';

// --- CONFIGURAÇÃO ---
$batch_size = 50; 
$tabela_de_tarefas = 'tarefas_pendentes_trafego';
// --------------------

echo "================================================\n<br>";
echo "INICIANDO WORKER DE TRÁFEGO (Versão Definitiva) - " . date('Y-m-d H:i:s') . "\n<br>";

// 1. Trava um lote de tarefas
$mysqli->query("UPDATE `$tabela_de_tarefas` SET status = 'processando', data_processamento = NOW() WHERE status = 'pendente' LIMIT $batch_size");
if ($mysqli->error) { die("Erro ao travar tarefas: " . $mysqli->error); }

// 2. Busca as tarefas travadas
$resultado = $mysqli->query("SELECT * FROM `$tabela_de_tarefas` WHERE status = 'processando'");
if ($mysqli->error) { die("Erro ao buscar tarefas travadas: " . $mysqli->error); }
$tarefas = $resultado->fetch_all(MYSQLI_ASSOC);
$resultado->free();

if (empty($tarefas)) {
    die("Nenhuma tarefa pendente encontrada. Encerrando.\n");
}

echo count($tarefas) . " tarefas travadas para processamento.\n<br>";

$token = obterTokenMeli();
if (!$token) { die("ERRO CRÍTICO: Não foi possível obter um token de acesso válido."); }

// Prepara a query de inserção, que sabemos estar correta
$stmt_insert = $mysqli->prepare("
    INSERT INTO trafego_diario (id_anuncio, data_metrica, cliques_ads, impressoes_ads, custo_ads, vendas_ads_qtd, visitas_totais, vendas_organicas_qtd, faturamento_total_ads)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
     cliques_ads = VALUES(cliques_ads), impressoes_ads = VALUES(impressoes_ads), custo_ads = VALUES(custo_ads),
     vendas_ads_qtd = VALUES(vendas_ads_qtd), visitas_totais = VALUES(visitas_totais), 
     vendas_organicas_qtd = VALUES(vendas_organicas_qtd), faturamento_total_ads = VALUES(faturamento_total_ads)
");

// 3. Processa cada tarefa do lote
foreach ($tarefas as $tarefa) {
    $id_tarefa = $tarefa['id'];
    $id_anuncio = $tarefa['id_anuncio'];
    $data_metrica = $tarefa['data_metrica'];

  // --- Início da Lógica de Visitas Corrigida ---

    $visitas_totais = 0; // Inicializa como 0 por segurança
    $url_visitas = "https://api.mercadolibre.com/items/$id_anuncio/visits/time_window?last=1&unit=day&ending=$data_metrica";
    $resp_visitas = fazerRequisicaoMeli($url_visitas, $token);
    
    // Verifica se a chamada foi bem-sucedida e se existe o array 'results'
    if ($resp_visitas['http_code'] == 200 && !empty($resp_visitas['body']['results'])) {
        // Percorre o array de resultados diários que a API retornou
        foreach ($resp_visitas['body']['results'] as $resultado_dia) {
            
            // Pega apenas a parte da data (AAAA-MM-DD) da resposta da API para comparar
            $data_resultado = substr($resultado_dia['date'], 0, 10); 

            // Compara com a data da tarefa que estamos processando
            if ($data_resultado == $data_metrica) {
                $visitas_totais = (int)$resultado_dia['total'];
                break; // Encontrou a data correta, pode parar o laço
            }
        }
    }
    sleep(1);

    // --- Fim da Lógica de Visitas Corrigida ---

    // Busca dados de Ads com a lógica validada
    $lista_de_metricas = "clicks,prints,cost,units_quantity,total_amount,organic_items_quantity";
    $url_ads = "https://api.mercadolibre.com/advertising/product_ads/items/$id_anuncio?date_from=$data_metrica&date_to=$data_metrica&metrics=$lista_de_metricas";
    $resp_ads = fazerRequisicaoMeli($url_ads, $token);
    
    $metricas_ads = $resp_ads['body']['metrics'] ?? [];
    
    $cliques_ads = (int)($metricas_ads['clicks'] ?? 0);
    $impressoes_ads = (int)($metricas_ads['prints'] ?? 0);
    $custo_ads = (float)($metricas_ads['cost'] ?? 0.0);
    $vendas_ads_qtd = (int)($metricas_ads['units_quantity'] ?? 0);
    $faturamento_total_ads = (float)($metricas_ads['total_amount'] ?? 0.0);
    $vendas_organicas_qtd = (int)($metricas_ads['organic_items_quantity'] ?? 0);

    echo "Processando Anúncio: <b>$id_anuncio</b> para <b>$data_metrica</b>... (Visitas_totais: $visitas_totais, Vendas Orgânicas: $vendas_organicas_qtd)<br>";
    
    $stmt_insert->bind_param("ssiidiiid", 
        $id_anuncio, $data_metrica, $cliques_ads, $impressoes_ads, $custo_ads, 
        $vendas_ads_qtd, $visitas_totais, $vendas_organicas_qtd, $faturamento_total_ads
    );
    $stmt_insert->execute();

    $mysqli->query("UPDATE `$tabela_de_tarefas` SET status = 'concluido' WHERE id = $id_tarefa");
    sleep(1);
}

echo "<br>Lote de tarefas de tráfego processado com sucesso.\n";

$stmt_insert->close();
$mysqli->close();
?>