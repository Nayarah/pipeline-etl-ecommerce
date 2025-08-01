<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(900);
require_once 'config.php';

// --- ⬇️ CONFIGURE AQUI OS ANÚNCIOS E PALAVRAS-CHAVE PARA MONITORAR ⬇️ ---
$alvos = [
    [
        'palavra_chave' => 'lanterna tatica t9',
        'id_anuncio_pai' => 'MLB2919416195'
    ],
    [
        'palavra_chave' => 'lanterna',
        'id_anuncio_pai' => 'MLB2919416195'
    ],
    [
        'palavra_chave' => 'faca tatica',
        'id_anuncio_pai' => 'MLB2608324433'
    ],
    [
        'palavra_chave' => 'faca tatica',
        'id_anuncio_pai' => 'MLB5306672480'
    ],
    [
        'palavra_chave' => 'faca tatica',
        'id_anuncio_pai' => 'MLB3174601455'
    ],
    [
        'palavra_chave' => 'faca',
        'id_anuncio_pai' => 'MLB2608324433'
    ],
    [
        'palavra_chave' => 'faca',
        'id_anuncio_pai' => 'MLB5306672480'
    ],
    [
        'palavra_chave' => 'faca',
        'id_anuncio_pai' => 'MLB3174601455'
    ],
];
// -----------------------------------------------------------------------

echo "Iniciando tarefa de monitoramento de posicionamento (v3 - Busca Pública)...\n<br>";
$token = obterTokenMeli();

$data_hoje = date('Y-m-d');
$stmt = $mysqli->prepare("
    INSERT INTO posicionamento_anuncios (data_verificacao, palavra_chave, id_anuncio_pai, posicao, pagina) 
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        posicao = VALUES(posicao),
        pagina = VALUES(pagina)
");

foreach ($alvos as $alvo) {
    $palavra_chave = $alvo['palavra_chave'];
    $id_anuncio_alvo = $alvo['id_anuncio_pai'];
    $posicao_encontrada = null;
    $pagina_encontrada = null;
    
    echo "<hr>Buscando por '<b>$palavra_chave</b>' para o anúncio <b>$id_anuncio_alvo</b>...<br>";

    // --- LÓGICA CORRIGIDA: BUSCA PÚBLICA COM PAGINAÇÃO ---
    for ($pagina = 1; $pagina <= 10; $pagina++) { // Busca até a 10ª página (500 resultados)
        $offset = ($pagina - 1) * 50;
        
        // Usa o endpoint de busca pública do site
        $url_busca = "https://api.mercadolibre.com/sites$id_anuncio_alvo/search?q=" . urlencode($palavra_chave) . "&offset=$offset&catalog_listing=false";
        
        $response = fazerRequisicaoMeli($url_busca, $token);
        
        if ($response['http_code'] == 200 && !empty($response['body']['results'])) {
            foreach ($response['body']['results'] as $index => $resultado) {
                if ($resultado['id'] == $id_anuncio_alvo) {
                    $posicao_encontrada = $offset + $index + 1;
                    $pagina_encontrada = $pagina;
                    echo "<b style='color:green;'>ENCONTRADO! Posição: $posicao_encontrada (Página: $pagina_encontrada)</b><br>";
                    goto fim_busca_publica; // Pula para o final do laço de páginas
                }
            }
        } else {
            // Se a API falhar ou não retornar mais resultados, para de buscar
            break;
        }
        sleep(2); // Pausa para não sobrecarregar a API
    }
    
    fim_busca_publica:
    
    if (is_null($posicao_encontrada)) {
        echo "<b style='color:red;'>NÃO ENCONTRADO</b> nos resultados da API para esta palavra-chave.<br>";
    }

    $stmt->bind_param("sssii", $data_hoje, $palavra_chave, $id_anuncio_alvo, $posicao_encontrada, $pagina_encontrada);
    $stmt->execute();
}

$stmt->close();
$mysqli->close();
echo "<br>✅ Monitoramento concluído.";
?>