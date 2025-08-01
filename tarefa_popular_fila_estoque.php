<?php
// tarefa_popular_fila_estoque.php (versão corrigida)
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once 'config.php';


echo "Iniciando: Populando a fila de tarefas de ESTOQUE...\n";

// A ÚNICA FONTE DA VERDADE AGORA É A SUA TABELA DE CATÁLOGO
$resultado = $mysqli->query("SELECT sku, id_produto_tiny FROM produtos_catalogo WHERE sku IS NOT NULL AND sku NOT LIKE 'EMB%' AND id_produto_tiny IS NOT NULL AND tipo_produto = 'S' OR tipo_produto = 'K'");
$produtos_para_verificar = $resultado->fetch_all(MYSQLI_ASSOC);
$resultado->free();

if (empty($produtos_para_verificar)) {
    die("Nenhum produto com SKU e ID Tiny encontrados no catálogo para criar tarefas.");
}

// Limpa tarefas antigas e pendentes antes de inserir as novas
//$mysqli->query("DELETE FROM tarefas_pendentes_estoque WHERE status = 'pendente'");

$stmt = $mysqli->prepare("INSERT INTO tarefas_pendentes_estoque (sku, id_produto_tiny, data_snapshot) VALUES (?, ?, ?)");
$count = 0;
$data_alvo = date('Y-m-d'); // Tarefas criadas para hoje

foreach ($produtos_para_verificar as $produto) {
    $sku = $produto['sku'];
    $id_tiny = $produto['id_produto_tiny'];
    
    $stmt->bind_param("sis", $sku, $id_tiny, $data_alvo);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $count++;
    }
}

echo "✅ Fila de estoque populada com $count novas tarefas.";
$stmt->close();
$mysqli->close();
?>


