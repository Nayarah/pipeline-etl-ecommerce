<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

echo "<h1>Teste de API de Visitas</h1>";

// --- ⬇️ CONFIGURE O TESTE AQUI ⬇️ ---
$id_anuncio_teste = 'MLB2919416195'; // SUBSTITUA PELO ID DO ANÚNCIO
$data_do_teste = '2025-06-29';     // SUBSTITUA PELA DATA NO FORMATO AAAA-MM-DD
// --------------------------------------------------

$token = obterTokenMeli();
if (!$token) {
    die("Não foi possível obter o token do Mercado Livre.");
}

// --- CHAMADA 1: Como está no seu script atualmente (com UTC) ---
$url_atual = "https://api.mercadolibre.com/items?ids=$id_anuncio_teste;

echo "<h2>1. Testando a Chamada Atual (com fuso UTC -00:00)</h2>";
echo "<p><b>URL:</b> " . htmlspecialchars($url_atual) . "</p>";

$response_atual = fazerRequisicaoMeli($url_atual, $token);
echo "<b>Resposta da API:</b>";
echo "<pre style='background:#f0f0f0; border:1px solid #ccc; padding:10px;'>";
print_r($response_atual['body']);
echo "</pre>";


// --- CHAMADA 2: Versão Corrigida (com fuso de Brasília -03:00) ---
// Removemos o parâmetro 'last=1' que é redundante e ajustamos o fuso

$url_corrigida = "https://api.mercadolibre.com/items/$id_anuncio_teste/visits?date_from={$data_do_teste}T00:00:00.000-03:00&date_to={$data_do_teste}T23:59:59.999-03:00";

echo "<hr><h2>2. Testando a Chamada Corrigida (com fuso BRT -03:00)</h2>";
echo "<p><b>URL:</b> " . htmlspecialchars($url_corrigida) . "</p>";

$response_corrigida = fazerRequisicaoMeli($url_corrigida, $token);
echo "<b>Resposta da API:</b>";
echo "<pre style='background:#e6ffe6; border:1px solid #5cb85c; padding:10px;'>";
print_r($response_corrigida['body']);
echo "</pre>";

?>