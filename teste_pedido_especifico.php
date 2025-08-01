<?php
// Arquivo: teste_ordem_especifica.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // 5 minutos
require_once 'config.php';

// --- DEFINA AQUI O ID DO PEDIDO A SER TESTADO ---
$id_ordem_teste = '2000008459278717';
// ---------------------------------------------

echo "<h1>Depurando Pedido Específico: $id_ordem_teste</h1>";

$token = obterTokenMeli();
if (!$token) { die("ERRO CRÍTICO: Não foi possível obter o token."); }

// --- ETAPA 1: BUSCAR DADOS DO PEDIDO ---
echo "<h2>1. Buscando dados do pedido na API...</h2>";
$url_ordem = "https://api.mercadolibre.com/orders/$id_ordem_teste";
$response_ordem = fazerRequisicaoMeli($url_ordem, $token);

if ($response_ordem['http_code'] != 200) {
    echo "Falha ao buscar os detalhes do pedido. Resposta da API:";
    echo "<pre>"; print_r($response_ordem); echo "</pre>";
    die();
}

$order = $response_ordem['body'];
echo "<h3>Resposta da API para o Pedido:</h3>";
echo "<pre style='background:#f0f0f0; border:1px solid #ccc; padding:10px;'>";
print_r($order);
echo "</pre>";

// --- ETAPA 2: PROCESSAR A LÓGICA DO SEU SCRIPT ---
echo "<hr><h2>2. Simulando o processamento do script...</h2>";

try {
    $stmt = $mysqli->prepare("
        INSERT INTO vendas_financeiro (id_ordem, id_anuncio, id_variacao, sku, data_venda, qtd_vendida, preco_unitario, faturamento_bruto_item, tarifa_ml, custo_frete_rateado, liquido_recebido, logistic_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        id_variacao = VALUES(id_variacao), sku = VALUES(sku), qtd_vendida = VALUES(qtd_vendida), preco_unitario = VALUES(preco_unitario), faturamento_bruto_item = VALUES(faturamento_bruto_item),
        tarifa_ml = VALUES(tarifa_ml), custo_frete_rateado = VALUES(custo_frete_rateado), liquido_recebido = VALUES(liquido_recebido), logistic_type = VALUES(logistic_type)
    ");

    $id_ordem = $order['id'];
    $data_venda = str_replace(['T', 'Z'], ' ', substr($order['date_created'], 0, 19));
    
    // Lógica de Frete
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
    }

    // Lógica de Rateio
    $faturamento_total_itens = 0;
    foreach($order['order_items'] as $item){
        $faturamento_total_itens += ($item['unit_price'] * $item['quantity']);
    }

    echo "<h3>Processando Itens do Pedido:</h3>";
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

        echo "<b>Preparando para inserir o item com SKU: $sku</b><br>";
        echo "Valores a serem inseridos: <pre>";
        print_r([
            'id_ordem' => $id_ordem, 'id_anuncio' => $id_anuncio, 'id_variacao' => $id_variacao, 'sku' => $sku,
            'data_venda' => $data_venda, 'qtd_vendida' => $qtd_vendida, 'preco_unitario' => $preco_unitario,
            'faturamento_bruto_item' => $faturamento_bruto_item, 'tarifa_ml' => $tarifa_ml,
            'custo_frete_rateado' => $custo_frete_rateado, 'liquido_recebido' => $liquido_recebido, 'logistic_type' => $logistic_type
        ]);
        echo "</pre>";

        $stmt->bind_param("issssiddddds",
            $id_ordem, $id_anuncio, $id_variacao, $sku, $data_venda, $qtd_vendida,
            $preco_unitario, $faturamento_bruto_item, $tarifa_ml, $custo_frete_rateado, $liquido_recebido, $logistic_type
        );
        
        if ($stmt->execute()) {
            echo "<b style='color:green;'>SUCESSO:</b> Item inserido/atualizado no banco.<br><hr>";
        } else {
             echo "<b style='color:red;'>ERRO NO EXECUTE:</b> " . $stmt->error . "<br><hr>";
        }
    }

    $stmt->close();
    $mysqli->close();
    echo "<h2>✅ Teste concluído.</h2>";

} catch (Exception $e) {
    echo "<h2><b style='color:red;'>ERRO FATAL CAPTURADO:</b></h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>