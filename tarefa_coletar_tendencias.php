<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
set_time_limit(300);
require_once 'config.php';

// --- ⬇️ CONFIGURE AQUI AS CATEGORIAS PARA MONITORAR ⬇️ ---
$categorias_alvo = [
    'MLB430264', // Exemplo para "Lanternas". Substitua pelo ID da sua categoria.
    // 'MLB...' // Você pode adicionar mais IDs de categoria aqui.
];
// -----------------------------------------------------------

echo "Iniciando tarefa de coleta de tendências de mercado...\n<br>";
$token = obterTokenMeli();

$data_hoje = date('Y-m-d');
$stmt = $mysqli->prepare("
    INSERT INTO tendencias_mercado (data_verificacao, id_categoria, palavra_chave, posicao_tendencia) 
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        posicao_tendencia = VALUES(posicao_tendencia)
");
foreach ($categorias_alvo as $id_categoria) {
    echo "<hr>Buscando tendências para a categoria <b>$id_categoria</b>...<br>";
    $url_tendencias = "https://api.mercadolibre.com/trends/MLB/$id_categoria";

    $response = fazerRequisicaoMeli($url_tendencias, $token);

    if ($response['http_code'] == 200 && !empty($response['body'])) {
        foreach ($response['body'] as $index => $tendencia) {
            $palavra_chave = $tendencia['keyword'];
            $posicao = $index + 1;
            echo "Posição $posicao: $palavra_chave <br>";
            $stmt->bind_param("sssi", $data_hoje, $id_categoria, $palavra_chave, $posicao);
            $stmt->execute();
        }
    }
    sleep(2);
}

$stmt->close();
$mysqli->close();
echo "<br>✅ Coleta de tendências concluída.";
?>