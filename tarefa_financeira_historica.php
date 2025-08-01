<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(1800); // 30 minutos
require_once 'config.php';

echo "Iniciando tarefa de coleta de HISTÓRICO de dados financeiros...\n<br>";

// --- ⬇️ CONFIGURE O PERÍODO HISTÓRICO A SER BUSCADO AQUI ⬇️ ---
$data_inicio_historico = '2025-04-02'; // Exemplo: para buscar o início de Junho
$data_fim_historico = '2025-04-02';
// -----------------------------------------------------

$data_inicio_obj = new DateTime($data_inicio_historico);
$data_fim_obj = new DateTime($data_fim_historico);

if ($data_inicio_obj > $data_fim_obj) {
    die("ERRO: A data de início não pode ser maior que a data de fim.");
}

$token = obterTokenMeli();
if (!$token) {
    die("ERRO CRÍTICO: Não foi possível obter um token de acesso válido.");
}

$stmt = $mysqli->prepare("
    INSERT INTO vendas_financeiro (id_ordem, id_anuncio, id_variacao, sku, data_venda, qtd_vendida, preco_unitario, faturamento_bruto_item, tarifa_ml, custo_frete_rateado, liquido_recebido, logistic_type)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    id_variacao = VALUES(id_variacao), sku = VALUES(sku), qtd_vendida = VALUES(qtd_vendida), preco_unitario = VALUES(preco_unitario), faturamento_bruto_item = VALUES(faturamento_bruto_item),
    tarifa_ml = VALUES(tarifa_ml), custo_frete_rateado = VALUES(custo_frete_rateado), liquido_recebido = VALUES(liquido_recebido), logistic_type = VALUES(logistic_type)
");

$data_atual = $data_inicio_obj;
while ($data_atual <= $data_fim_obj) {
    
    $data_alvo_str = $data_atual->format('Y-m-d');
    $data_inicio_iso = $data_alvo_str . 'T00:00:00.000-03:00';
    $data_fim_iso = $data_alvo_str . 'T23:59:59.999-03:00';
    echo "<hr>Coletando pedidos para o dia: <b>$data_inicio_iso a $data_fim_iso</b>\n<br>";

    $allOrders = [];
    $offset = 0;
    $limit = 50;
    $hasMorePages = true;

    while ($hasMorePages) {
        $url = "https://api.mercadolibre.com/orders/search?seller=" . MELI_USER_ID . "&order.date_created.from=$data_inicio_iso&order.date_created.to=$data_fim_iso&offset=$offset&limit=$limit";
        $response = fazerRequisicaoMeli($url, $token);
        if ($response['http_code'] == 200 && !empty($response['body']['results'])) {
            $orders = $response['body']['results'];
            $allOrders = array_merge($allOrders, $orders);
            $offset += count($orders);
            if ($offset >= ($response['body']['paging']['total'] ?? 0)) {
                $hasMorePages = false;
            }
        } else {
            $hasMorePages = false;
        }
        sleep(1);
    }

    if (empty($allOrders)) {
        echo "Nenhum pedido encontrado para o dia.<br>";
        $data_atual->modify('+1 day');
        continue;
    }
    
    echo "Encontrados " . count($allOrders) . " pedidos. Processando...\n<br>";

    foreach ($allOrders as $order) {
        $id_ordem = $order['id'];
        $data_venda = str_replace(['T', 'Z'], ' ', substr($order['date_created'], 0, 19));
        
        $custo_frete_vendedor = 0;
        $logistic_type = 'N/A';

        if (isset($order['shipping']['id'])) {
            $shipping_id = $order['shipping']['id'];
            $url_frete = "https://api.mercadolibre.com/shipments/$shipping_id";
            $response_frete = fazerRequisicaoMeli($url_frete, $token);
            
            if ($response_frete['http_code'] == 200) {
                $logistic_type = $response_frete['body']['logistic_type'] ?? 'N/A';
                if ($logistic_type != 'self_service') {
                    $custo_frete_vendedor = $response_frete['body']['shipping_option']['list_cost'] ?? 0;
                }
            }
            sleep(1);
        }

        $faturamento_total_pedido = 0;
        if(!empty($order['payments'])){
            $faturamento_total_pedido = $order['payments'][0]['transaction_amount'] ?? 0;
        }
        
        $faturamento_total_itens = 0;
        foreach($order['order_items'] as $item){
            $faturamento_total_itens += ($item['unit_price'] * $item['quantity']);
        }
        
        foreach ($order['order_items'] as $item) {
            $id_anuncio = $item['item']['id'];
            $id_variacao = $item['item']['variation_id'] ?? null;
            $sku = $item['item']['seller_sku'] ?? '';
            $qtd_vendida = $item['quantity'];
            $preco_unitario = (float) $item['unit_price'];
            
            $faturamento_bruto_item = $preco_unitario * $qtd_vendida;
            $tarifa_ml = (float) ($item['sale_fee'] ?? 0);
            
            $proporcao_item = ($faturamento_total_itens > 0) ? ($faturamento_bruto_item / $faturamento_total_itens) : 0;
            $custo_frete_rateado = $custo_frete_vendedor * $proporcao_item;
            
            $liquido_recebido = $faturamento_bruto_item - $tarifa_ml - $custo_frete_rateado;

            $stmt->bind_param("issssiddddds",
                $id_ordem, $id_anuncio, $id_variacao, $sku, $data_venda, $qtd_vendida,
                $preco_unitario, $faturamento_bruto_item, $tarifa_ml, $custo_frete_rateado, $liquido_recebido, $logistic_type
            );
            $stmt->execute();
        }
        echo "Processando pedido (ID: $id_ordem)$data_venda... Itens inseridos/atualizados.<br>";
    }
    
    $data_atual->modify('+1 day');
}

echo "<br>✅ Tarefa de coleta de histórico financeiro concluída!";
$stmt->close();
$mysqli->close();
?>