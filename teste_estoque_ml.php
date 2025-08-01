<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

echo "<h1>Teste de API de Visitas (Método 'ending')</h1>";

// --- ⬇️ CONFIGURE O TESTE AQUI ⬇️ ---
$id_anuncio_teste = 'MLB2919416195'; // O anúncio que teve 789 visitas
$data_do_teste = '2025-07-14';     // A data em questão (AAAA-MM-DD)
// --------------------------------------------------

$token = obterTokenMeli();
if (!$token) {
    die("Não foi possível obter o token do Mercado Livre.");
}

// --- CHAMADA COM O MÉTODO CORRETO E VALIDADO ---
$url_corrigida = "https://api.mercadolibre.com/items/$id_anuncio_teste/visits/time_window?last=1&unit=day&ending=$data_do_teste";

echo "<h2>Testando a Chamada com o parâmetro 'ending'</h2>";
echo "<p><b>URL:</b> " . htmlspecialchars($url_corrigida) . "</p>";

$response_corrigida = fazerRequisicaoMeli($url_corrigida, $token);
echo "<b>Resposta da API:</b>";
echo "<pre style='background:#e6ffe6; border:1px solid #5cb85c; padding:10px;'>";
print_r($response_corrigida['body']);
echo "</pre>";

?>