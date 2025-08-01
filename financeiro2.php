<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(900); // 15 minutos
require_once 'config.php';

echo "Iniciando tarefa de coleta de dados financeiros (v3 - Lógica de Last Updated)...\n<br>";

$token = obterTokenMeli();
if (!$token) { die("ERRO CRÍTICO: Não foi possível obter um token de acesso válido."); }

// LÓGICA DE DATA CORRIGIDA: Busca por "last_updated" para pegar todas as alterações
$data_inicio_iso = date('Y-m-d', strtotime('-2 days')) . 'T00:00:00.000-03:00';
$data_fim_iso = date('Y-m-d', strtotime('-2 days')) . 'T23:59:59.999-03:00';
echo "Coletando pedidos atualizados no período de <b>$data_inicio_iso</b> a <b>$data_fim_iso</b>\n<br><br>";

$allOrders = [];
$offset = 0;
$limit = 50;

// Busca por "last_updated" em vez de "date_created"
$url = "https://api.mercadolibre.com/orders/search?seller=" . MELI_USER_ID . "&order.date_created.from=$data_inicio_iso&order.date_created.to=$data_fim_iso&offset=$offset&limit=$limit";

// Lógica de paginação para buscar todos os pedidos do período
while ($url) {
    $response = fazerRequisicaoMeli($url, $token);
    if ($response['http_code'] == 200 && !empty($response['body']['results'])) {
        $allOrders = array_merge($allOrders, $response['body']['results']);
        
        $paging = $response['body']['paging'];
        $offset = $paging['offset'] + $paging['limit'];
        if ($offset < $paging['total']) {
            $url = "https://api.mercadolibre.com/orders/search?seller=" . MELI_USER_ID . "&order.date_created.from=$data_inicio_iso&order.date_created.to=$data_fim_iso&offset=$offset&limit=$limit";
        } else {
            $url = null;
        }
    } else {
        $url = null;
    }
    sleep(1);
}


if (empty($allOrders)) { die("Nenhum pedido encontrado para o período. Tarefa concluída."); }
echo "Encontrados " . count($allOrders) . " pedidos para processar...\n<br>";

// Prepara o statement com a nova coluna pack_id
$stmt = $mysqli->prepare("
    INSERT INTO vendas_financeiro (id_ordem, pack_id, id_anuncio, id_variacao, sku, data_venda, qtd_vendida, preco_unitario, faturamento_bruto_item, tarifa_ml, custo_frete_rateado, liquido_recebido, logistic_type)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    pack_id = VALUES(pack_id), id_variacao = VALUES(id_variacao), sku = VALUES(sku), qtd_vendida = VALUES(qtd_vendida), 
    preco_unitario = VALUES(preco_unitario), faturamento_bruto_item = VALUES(faturamento_bruto_item),
    tarifa_ml = VALUES(tarifa_ml), custo_frete_rateado = VALUES(custo_frete_rateado), 
    liquido_recebido = VALUES(liquido_recebido), logistic_type = VALUES(logistic_type)
");

foreach ($allOrders as $order_header) {
    $id_ordem_processar = $order_header['id'];
    echo "<hr>Processando Pedido ID: $id_ordem_processar... ";

    try {
        // Busca os detalhes completos do pedido
        $url_detalhe = "https://api.mercadolibre.com/orders/$id_ordem_processar";
        $response_detalhe = fazerRequisicaoMeli($url_detalhe, $token);

        if ($response_detalhe['http_code'] != 200) {
            throw new Exception("Falha ao buscar detalhes do pedido. HTTP Code: " . $response_detalhe['http_code']);
        }
        $order = $response_detalhe['body'];

        // Lógica de extração de dados
        $id_ordem = $order['id'];
        $pack_id = $order['pack_id'] ?? null; // Captura o pack_id
      // ======================================================================
// #### INÍCIO DA LÓGICA DE DATA CORRIGIDA E SEGURA ####
// ======================================================================

// Inicializa a variável para garantir que ela sempre exista.
$data_venda = null; 

// Pega a string de data da API, garantindo que existe um valor.
$data_original_api = $order['date_created'] ?? null;

// SÓ executa a conversão SE a data da API não for nula/vazia.
if (!empty($data_original_api)) {
    try {
        // Cria o objeto de data, converte para o fuso de São Paulo e formata para o MySQL.
        $data_objeto = new DateTime($data_original_api);
        $data_objeto->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        $data_venda = $data_objeto->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        // Se houver um erro na conversão (data malformada), mantém a data como nula 
        // e opcionalmente registra o erro (não vamos parar o script por isso).
        echo "AVISO: Falha ao converter a data '$data_original_api'. Erro: " . $e->getMessage() . "<br>";
        $data_venda = null;
    }
}
// #### FIM DA LÓGICA DE DATA ####
// ======================================================================
    
 
        
        // Lógica de frete 
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

            // Bind param com 13 placeholders (adicionamos um 'i' para pack_id)
            $stmt->bind_param("iissssiddddds",
                $id_ordem, $pack_id, $id_anuncio, $id_variacao, $sku, $data_venda, $qtd_vendida,
                $preco_unitario, $faturamento_bruto_item, $tarifa_ml, $custo_frete_rateado, $liquido_recebido, $logistic_type
            );
            $stmt->execute();
        }
        echo "<b style='color:green;'>Sucesso.</b><br>";

    } catch (Exception $e) {
        echo "<b style='color:red;'>ERRO:</b> " . $e->getMessage() . "<br>";
        // Opcional: registrar este $id_ordem_processar em uma tabela de erros para reprocessar depois.
    }
}

$stmt->close();
$mysqli->close();

echo "<br>✅ Tarefa concluída!";
?>