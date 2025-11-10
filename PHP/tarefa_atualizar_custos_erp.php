<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(900); // 15 minutos
require_once 'config.php';

echo "Iniciando tarefa de atualização de custos de produtos do TinyERP...\n<br>";

// 1. Buscar todos os SKUs distintos da nossa NOVA tabela de mapeamento
$skus_para_atualizar = [];
$resultado = $mysqli->query("SELECT DISTINCT sku_produto FROM anuncios_canais WHERE sku_produto IS NOT NULL AND sku_produto != ''");
while ($linha = $resultado->fetch_assoc()) {
    $skus_para_atualizar[] = $linha['sku_produto'];
}
$resultado->free();

if (empty($skus_para_atualizar)) {
    die("Nenhum SKU encontrado na tabela 'anuncios_canais' para atualizar.");
}

$total_skus = count($skus_para_atualizar);
echo "Encontrados $total_skus SKUs únicos para verificar no TinyERP...\n<br>";
$contador = 0;

// 2. Preparar o SQL para ATUALIZAR o custo na nossa tabela de CATÁLOGO
$stmt = $mysqli->prepare("UPDATE produtos_catalogo SET custo_produto = ? WHERE sku = ?");

// 3. Loop através de cada SKU para buscar seu custo no Tiny
foreach ($skus_para_atualizar as $sku) {
    $contador++;
    echo "Processando SKU $contador de $total_skus ($sku)... ";

    $postData = http_build_query([
        'token' => TINY_API_TOKEN,
        'formato' => 'json',
        'pesquisa' => $sku
    ]);
    $response = fazerRequisicaoCurl('https://api.tiny.com.br/api2/produtos.pesquisa.php', $postData);
    $dataTiny = json_decode($response, true);

    // 4. Verifica a resposta e atualiza o banco de dados
    if (isset($dataTiny['retorno']['status']) && $dataTiny['retorno']['status'] == 'OK' && !empty($dataTiny['retorno']['produtos'])) {
        $custo_produto = $dataTiny['retorno']['produtos'][0]['produto']['preco_custo'] ?? 0.00;
        
        $stmt->bind_param("ds", $custo_produto, $sku);
        $stmt->execute();
        
        echo "Custo encontrado: R$ $custo_produto. Atualizado!\n<br>";
    } else {
        $erro_msg = 'SKU não encontrado no Tiny ou sem custo definido.';
        echo "Não foi possível atualizar: $erro_msg\n<br>";
    }
    
    sleep(1); 
}

$stmt->close();
$mysqli->close();

echo "<br>✅ Tarefa de atualização de custos concluída!";
?>