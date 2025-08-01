<?php
// Arquivo: callback.php
// Responsabilidade: Receber o código do Meli, trocá-lo pelos tokens e salvar o arquivo meli_token.json.
// ESTE ARQUIVO É CHAMADO AUTOMATICAMENTE PELO MERCADO LIVRE. NÃO ACESSE DIRETAMENTE.

require_once 'config.php';

// 1. Verifica se o Mercado Livre enviou o código de autorização (TG-...)
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    echo "Código de autorização recebido. Trocando pelo Access Token...\n<br>";

    // 2. Prepara a requisição para trocar o código pelo token
    $url = 'https://api.mercadolibre.com/oauth/token';
    $post_data = [
        'grant_type' => 'authorization_code',
        'client_id' => MELI_CLIENT_ID,
        'client_secret' => MELI_CLIENT_SECRET,
        'code' => $code,
        'redirect_uri' => MELI_REDIRECT_URI
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $token_data = json_decode($response, true);

    // 3. Se a troca foi bem-sucedida, salva os dados no arquivo meli_token.json
    if (isset($token_data['access_token']) && isset($token_data['refresh_token'])) {
        // Adiciona um campo para sabermos quando o token expira
        $token_data['expires_at'] = time() + $token_data['expires_in'];

        // Salva o arquivo JSON formatado para fácil leitura
        file_put_contents('meli_token.json', json_encode($token_data, JSON_PRETTY_PRINT));
        
        echo "<h1>Sucesso!</h1>";
        echo "<p>O arquivo <strong>meli_token.json</strong> foi criado com sucesso.</p>";
        echo "<p>Você já pode fechar esta página e executar suas tarefas.</p>";

    } else {
        echo "<h1>Erro!</h1>";
        echo "<p>Não foi possível obter os tokens. Resposta da API:</p>";
        echo "<pre>" . print_r($token_data, true) . "</pre>";
    }

} else {
    echo "<h1>Erro: Nenhum código de autorização recebido.</h1>";
}
?>