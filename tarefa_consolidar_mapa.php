<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(900); // 15 minutos
require_once 'config.php';

echo "Iniciando consolidação do mapa (Lógica: Anúncio -> Catálogo)...\n";

// 1. Carregar todos os produtos e anúncios para a memória
echo "Carregando produtos do catálogo...\n";
$produtos_result = $mysqli->query("SELECT sku, id_produto_tiny, ean, nome_produto FROM produtos_catalogo");
// Transforma o array de produtos em um mapa para busca rápida por SKU
$mapa_produtos_por_sku = [];
while ($produto = $produtos_result->fetch_assoc()) {
    if (!empty($produto['sku'])) {
        $mapa_produtos_por_sku[$produto['sku']] = $produto;
    }
}
$produtos_result->free();

echo "Carregando canais de anúncio...\n";
$anuncios_result = $mysqli->query("SELECT id_anuncio_pai, id_anuncio_canal, sku_produto, categoria_anuncio, logistic_type FROM anuncios_canais WHERE sku_produto IS NOT NULL AND sku_produto != ''");
$anuncios = $anuncios_result->fetch_all(MYSQLI_ASSOC);
$anuncios_result->free();

echo "Dados carregados. Iniciando mapeamento...\n<hr>";

// Prepara o statement para inserir ou atualizar o mapa
$stmt_insert = $mysqli->prepare("
    INSERT INTO mapa_produtos_anuncios (sku, id_produto_tiny, ean, titulo_produto, id_anuncio_canal, id_anuncio_pai, categoria, logistic_type, plataforma) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'meli_lare') 
    ON DUPLICATE KEY UPDATE 
        id_produto_tiny=VALUES(id_produto_tiny),
        ean=VALUES(ean), 
        titulo_produto=VALUES(titulo_produto),
        id_anuncio_canal=VALUES(id_anuncio_canal),
        id_anuncio_pai=VALUES(id_anuncio_pai),
        logistic_type=VALUES(logistic_type),
        categoria=VALUES(categoria),
         data_atualizacao=NOW()
");

$alertas = [];
$mapeados = 0;
$erros_execucao = 0;

// 2. Loop principal para mapear (começando pelos anúncios, como você sugeriu)
foreach ($anuncios as $anuncio) {
    $sku_anuncio = $anuncio['sku_produto'];
    
    // Verifica se o SKU do anúncio existe no nosso mapa de produtos do catálogo
    if (isset($mapa_produtos_por_sku[$sku_anuncio])) {
        // SUCESSO: Encontrou o produto correspondente no catálogo
        $produto_encontrado = $mapa_produtos_por_sku[$sku_anuncio];
        
        $stmt_insert->bind_param("ssssssss", 
            $sku_anuncio,
            $produto_encontrado['id_produto_tiny'],
            $produto_encontrado['ean'], 
            $produto_encontrado['nome_produto'], 
            $anuncio['id_anuncio_canal'],
            $anuncio['id_anuncio_pai'],
            $anuncio['categoria_anuncio'],
            $anuncio['logistic_type']
            
        );
        
        if ($stmt_insert->execute()) {
            if ($stmt_insert->affected_rows > 0) {
                $mapeados++;
            }
        } else {
            $erros_execucao++;
        }
    } else {
        // FALHA: SKU do anúncio não foi encontrado no catálogo. Gera alerta.
        $alertas[] = "O anúncio '{$anuncio['id_anuncio_pai']}' possui o SKU '{$sku_anuncio}', mas este SKU não foi encontrado na tabela 'produtos_catalogo'.";
    }
}

echo "Consolidação finalizada.<br>\n";
echo "$mapeados registros mapeados/atualizados com sucesso.<br>\n";
if ($erros_execucao > 0) {
    echo "$erros_execucao erros durante a execução do banco de dados.<br>\n";
}

// 3. Envio de Alertas por e-mail
if (!empty($alertas)) {
    echo "<hr>Enviando alertas de falha por e-mail...<br>\n";
    $corpo_email = "Olá,\n\nOcorreram as seguintes falhas na consolidação do mapa de produtos:\n\n";
    $corpo_email .= implode("\n- ", $alertas);
    $corpo_email .= "\n\nPor favor, verifique os SKUs na tabela 'produtos_catalogo' ou o cadastro dos anúncios no Mercado Livre.";
    
    $to = "contato@lareoff.com.br";
    $subject = "Alerta de Falha na Consolidação de Produtos";
    $headers = "From: automacao@lareoff.com.br" . "\r\n" .
               "Reply-To: no-reply@lareoff.com.br" . "\r\n" .
               "X-Mailer: PHP/" . phpversion();

    mail($to, $subject, $corpo_email, $headers);
    
    echo "Alertas de falha enviados por e-mail para $to.<br>\n";
} else {
    echo "Nenhuma falha de mapeamento encontrada.<br>\n";
}

$stmt_insert->close();
$mysqli->close();

echo "<hr>✅ Tarefa concluída!";
?>