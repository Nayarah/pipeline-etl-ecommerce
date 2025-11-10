<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once 'config.php';

echo "Iniciando: Populando a fila de tarefas de sincronização de catálogo (v2)...\n<br>";

// --- ETAPA 1: LIMPAR A FILA ANTIGA ---
echo "Limpando tarefas antigas da fila de catálogo...\n<br>";
$mysqli->query("TRUNCATE TABLE tarefas_pendentes_catalogo");
echo "Fila limpa com sucesso.\n<br>";

// --- ETAPA 2: BUSCAR OS SKUs ATUAIS DO TINY ---
$all_product_ids = [];
$pagina_atual = 1;
$hasMorePages = true;

while ($hasMorePages) {
    echo "Buscando página $pagina_atual de produtos no Tiny...<br>";
    $postDataPesquisa = http_build_query(['token' => TINY_API_TOKEN, 'formato' => 'json', 'pagina' => $pagina_atual]);
    $responsePesquisa = fazerRequisicaoCurl('https://api.tiny.com.br/api2/produtos.pesquisa.php', $postDataPesquisa);
    $dataPesquisa = json_decode($responsePesquisa, true);

    if (isset($dataPesquisa['retorno']['status']) && $dataPesquisa['retorno']['status'] == 'OK' && !empty($dataPesquisa['retorno']['produtos'])) {
        foreach ($dataPesquisa['retorno']['produtos'] as $item) {
            $all_product_ids[] = (int)$item['produto']['id'];
        }
        $pagina_atual++;
    } else {
        $hasMorePages = false;
    }
    sleep(1);
}

if (empty($all_product_ids)) {
    die('Nenhum produto encontrado no Tiny para criar tarefas.');
}

// --- ETAPA 3: INSERIR AS NOVAS TAREFAS ---
echo "Total de " . count($all_product_ids) . " produtos encontrados. Inserindo na fila de tarefas...\n<br>";
$stmt = $mysqli->prepare("INSERT INTO tarefas_pendentes_catalogo (id_produto_tiny) VALUES (?)");
$count = 0;
foreach ($all_product_ids as $id_produto) {
    $stmt->bind_param("i", $id_produto);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $count++;
    }
}

echo "✅ Fila populada com $count novas tarefas.";
$stmt->close();
$mysqli->close();
?>