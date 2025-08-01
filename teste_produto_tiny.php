<?php
// Arquivo: teste_api_tiny.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Inclui o config para usar sua função de requisição e o token
require_once 'config.php'; 

$id_produto_tiny_problematico = 1065306232;

echo "<h1>Teste de API para o Produto Tiny ID: $id_produto_tiny_problematico</h1>";

// Monta a requisição exatamente como o outro script faz
$postData = http_build_query([
    'token' => TINY_API_TOKEN,
    'formato' => 'json',
    'id' => $id_produto_tiny_problematico
]);

// Faz a chamada para a API
echo "<h2>Fazendo requisição para produto.obter.php...</h2>";
$responseJson = fazerRequisicaoCurl('https://api.tiny.com.br/api2/produto.obter.php', $postData);

// Imprime a resposta CRUA (em formato JSON) que o Tiny devolveu
echo "<h2>Resposta Crua (JSON) Recebida do Tiny:</h2>";
echo "<pre style='background-color:#f0f0f0; padding:15px; border:1px solid #ccc; white-space: pre-wrap;'>";
echo htmlspecialchars($responseJson);
echo "</pre>";

// Tenta decodificar o JSON e imprime a estrutura do array para análise
echo "<h2>Estrutura do Array PHP (após json_decode):</h2>";
$dataArray = json_decode($responseJson, true);
echo "<pre style='background-color:#f0f0f0; padding:15px; border:1px solid #ccc;'>";
print_r($dataArray);
echo "</pre>";

?>