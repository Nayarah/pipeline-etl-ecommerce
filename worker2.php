<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

echo "<h1>Teste de Busca Pública na API do Mercado Livre</h1>";

// --- ⬇️ CONFIGURE O TESTE AQUI ⬇️ ---
$palavra_chave_teste = 'lanterna tatica t9';
$id_anuncio_alvo = 'MLB2919416195';
// --------------------------------------------------

$token = obterTokenMeli();
if (!$token) {
    die("Não foi possível obter o token.");
}

echo "Buscando na API por: '<b>" . htmlspecialchars($palavra_chave_teste) . "</b>'<br>";
echo "Procurando pelo ID do anúncio: <b>$id_anuncio_alvo</b><br>";

// Monta a URL para buscar a PRIMEIRA PÁGINA de resultados públicos
$url_busca = "https://api.mercadolibre.com/sites/MLB/search?q=" . urlencode($palavra_chave_teste) . "&offset=0";

echo "<p><b>URL da API chamada:</b> " . htmlspecialchars($url_busca) . "</p>";

$response = fazerRequisicaoMeli($url_busca, $token);

if ($response['http_code'] == 200 && !empty($response['body']['results'])) {
    
    echo "<hr><h2>Resposta Completa da API (para depuração):</h2>";
    echo "<p>Total de resultados encontrados: " . ($response['body']['paging']['total'] ?? 'N/A') . "</p>";
    echo "<pre style='background:#f0f0f0; border:1px solid #ccc; padding:10px; max-height: 400px; overflow-y: scroll;'>";
    print_r($response['body']);
    echo "</pre>";

    $resultados = $response['body']['results'];
    $posicao_encontrada = null;

    echo "<hr><h2>Análise dos Resultados da Primeira Página:</h2>";
    
    foreach ($resultados as $index => $resultado) {
        $id_resultado_atual = $resultado['id'];
        $posicao_atual = $index + 1;
        
        echo "Posição $posicao_atual: ID <b>$id_resultado_atual</b> ... ";
        
        if ($id_resultado_atual == $id_anuncio_alvo) {
            $posicao_encontrada = $posicao_atual;
            echo "<b style='color:green;'>ENCONTRADO!</b>";
        }
        echo "<br>";
    }
    
    echo "<hr><h3>Conclusão:</h3>";
    if ($posicao_encontrada) {
        echo "<b style='color:green;'>O anúncio foi encontrado na posição $posicao_encontrada.</b>";
    } else {
        echo "<b style='color:red;'>O anúncio NÃO foi encontrado na lista de resultados da primeira página retornada pela API.</b>";
    }

} else {
    echo "<h2><b style='color:red;'>Falha na chamada da API ou nenhum resultado encontrado.</b></h2>";
    echo "<pre>";
    print_r($response);
    echo "</pre>";
}

$mysqli->close();
?>