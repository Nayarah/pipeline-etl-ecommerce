<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(900); // 15 minutos
require_once 'config.php';

echo "Iniciando tarefa de sincronização de anúncios (vFINAL - com Scroll)...\n<br>";

$data_hora_atual = date('Y-m-d H:i:s');

$token = obterTokenMeli();
if (!$token) { die("Não foi possível obter o token."); }

// --- ETAPA 1: Buscar a lista de TODOS os IDs de anúncios usando o método SCROLL ---
$all_item_ids = [];
$scroll_id = null;
$limit = 50;

echo "Buscando lista de anúncios ativos...\n<br>";

do {
    $url = "https://api.mercadolibre.com/users/" . MELI_USER_ID . "/items/search?search_type=scan&limit=$limit";
    if ($scroll_id) {
        $url .= "&scroll_id=$scroll_id";
    }

    $response = fazerRequisicaoMeli($url, $token);

    if ($response['http_code'] == 200 && !empty($response['body']['results'])) {
        $all_item_ids = array_merge($all_item_ids, $response['body']['results']);
        $scroll_id = $response['body']['scroll_id'] ?? null;
    } else {
        $scroll_id = null;
    }
    sleep(1);

} while ($scroll_id);


$total_anuncios = count($all_item_ids);
echo "Encontrados $total_anuncios anúncios ativos no total para processar...\n<br><hr>";

// Se não encontrou anúncios, para aqui.
if($total_anuncios == 0){
    die("Nenhum anúncio encontrado. Tarefa finalizada.");
}

// --- ETAPA 2: Preparar o SQL (continua o mesmo) ---
$stmt = $mysqli->prepare("
    INSERT INTO anuncios_canais (id_anuncio_canal, canal_venda, id_anuncio_pai, sku_produto, titulo_anuncio, id_categoria, categoria_anuncio, status, logistic_type, data_atualizacao)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    id_anuncio_pai = VALUES(id_anuncio_pai), sku_produto = VALUES(sku_produto), titulo_anuncio = VALUES(titulo_anuncio),
    id_categoria = VALUES(id_categoria), categoria_anuncio = VALUES(categoria_anuncio), status = VALUES(status), logistic_type = VALUES(logistic_type), data_atualizacao = VALUES (data_atualizacao)
");

// --- ETAPA 3: Loop para detalhar cada anúncio (Lógica de SKU e Variações Corrigida) ---
$contador = 0;
$anuncios_salvos = 0;

foreach ($all_item_ids as $item_id) {
    $contador++;
    echo "Processando anúncio $contador de $total_anuncios ($item_id)... ";

    $url_detalhe = "https://api.mercadolibre.com/items/$item_id?include_attributes=all";
    $response_detalhe = fazerRequisicaoMeli($url_detalhe, $token);

    if ($response_detalhe['http_code'] == 200) {
        $anuncio = $response_detalhe['body'];
        // Dados comuns ao anúncio pai
        $id_anuncio_pai = $anuncio['id'];
        $titulo = $anuncio['title'];
        $status = $anuncio['status'];
        $categoria_id = $anuncio['category_id'] ?? null;
        $canal = 'Mercado Livre';
        $logistic_type = $anuncio['shipping']['logistic_type'] ?? 'default';
        $nome_categoria = 'N/A';
        
        if ($categoria_id) {
            $url_categoria = "https://api.mercadolibre.com/categories/$categoria_id";
            $response_categoria = fazerRequisicaoMeli($url_categoria, $token);
            if ($response_categoria['http_code'] == 200) {
                $nome_categoria = $response_categoria['body']['name'] ?? 'N/A';
            }
        }
        
        // **INÍCIO DA LÓGICA CORRETA**
        // Primeiro, verifica se o anúncio TEM variações
        if (!empty($anuncio['variations'])) {
            // Se tiver, percorre cada uma delas
            foreach ($anuncio['variations'] as $variacao) {
                $id_variacao = $variacao['id'];
                $sku_produto = null;

                // Gaveta 1: Procura o SELLER_SKU nos atributos da VARIAÇÃO
                if (!empty($variacao['attributes'])) {
                    foreach ($variacao['attributes'] as $attribute) {
                        if (isset($attribute['id']) && $attribute['id'] == 'SELLER_SKU') {
                            $sku_produto = $attribute['value_name'];
                            break;
                        }
                    }
                }
                
                // Gaveta 2: Se não achou, procura no seller_custom_field da VARIAÇÃO
                if (empty($sku_produto) && !empty($variacao['seller_custom_field'])) {
                    $sku_produto = $variacao['seller_custom_field'];
                }

                // Se encontrou um SKU para esta variação, salva no banco
                if ($sku_produto) {
                    $stmt->bind_param("ssssssssss", $id_variacao, $canal, $id_anuncio_pai, $sku_produto, $titulo, $categoria_id, $nome_categoria, $status, $logistic_type, $data_hora_atual);
                    $stmt->execute();
                    if($stmt->affected_rows > 0) { $anuncios_salvos++; }
                }
                
                 echo "OK.$sku_produto $id_variacao<br>";
            }
           
            
            
        } else { // Se não tiver variações, processa como um anúncio simples
            $sku_produto = null;
            $id_variacao = null;
            
            // Gaveta 1: Procura o SELLER_SKU nos atributos do anúncio PAI
            if (!empty($anuncio['attributes'])) {
                foreach ($anuncio['attributes'] as $attribute) {
                    if (isset($attribute['id']) && $attribute['id'] == 'SELLER_SKU') {
                        $sku_produto = $attribute['value_name'];
                        break;
                    }
                }
            }

            // Gaveta 2: Se não achou, procura no seller_custom_field do anúncio PAI
            if (empty($sku_produto) && !empty($anuncio['seller_custom_field'])) {
                $sku_produto = $anuncio['seller_custom_field'];
            }
            
            // Filtra SKUs de confronto e salva no banco
            if ($sku_produto) {
                $stmt->bind_param("ssssssssss", $id_anuncio_pai, $canal, $id_anuncio_pai, $sku_produto, $titulo, $categoria_id, $nome_categoria, $status, $logistic_type, $data_hora_atual);
                $stmt->execute();
                if($stmt->affected_rows > 0) { $anuncios_salvos++; }
            }
            
            echo "OK.$sku_produto Produto sem variações<br>";
        }
        
    } else {
        echo "Falha ao buscar detalhes.<br>";
    }
    sleep(1); 
}

echo "<hr>Total de anúncios/variações salvos/atualizados no banco: $anuncios_salvos<hr>";
        

$stmt->close();
$mysqli->close();
echo "<hr>✅ Tarefa de sincronização de anúncios concluída!";
?>