<?php
// ======================================================================================================
// --- ⚙️ CONFIGURAÇÕES GLOBAIS E FUNÇÕES DE SUPORTE (VERSÃO ÚNICA E COMPLETA) ---
// ======================================================================================================

// Força a exibição de erros para ajudar a encontrar problemas.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- CREDENCIAIS DO BANCO DE DADOS ---
define('DB_HOST', 'localhost');
define('DB_USER', 'SEU_USUARIO_DO_BANCO_DE_DADOS');
define('DB_PASS', 'SUA_SENHA);
define('DB_NAME', 'SEU_BANCO_DE_DADOS');

// --- CREDENCIAIS DO TINY ERP ---
define('TINY_API_TOKEN', '');

// --- CREDENCIAIS DO MERCADO LIVRE ---
define('MELI_CLIENT_ID', '');
define('MELI_CLIENT_SECRET', '');
define('MELI_USER_ID', '');
define('MELI_REDIRECT_URI', 'https://automacao.lareoff.com.br/callback.php');

// --- INICIA A CONEXÃO COM O BANCO DE DADOS ---
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die('Erro de Conexão com o Banco de Dados: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

// ======================================================================================================
// --- FUNÇÕES DE SUPORTE ---
// ======================================================================================================

function obterTokenMeli() {
    global $mysqli;
    $token_file_path = __DIR__ . '/meli_token.json';

    if (!file_exists($token_file_path)) {
        die("ERRO CRÍTICO: O arquivo 'meli_token.json' não foi encontrado. Execute 'autorizar.php'.");
    }
    $token_data = json_decode(file_get_contents($token_file_path), true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($token_data['refresh_token'])) {
        die("ERRO CRÍTICO: O arquivo 'meli_token.json' está corrompido.");
    }
    
    if (time() > ($token_data['expires_at'] - 300)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.mercadolibre.com/oauth/token');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type'    => 'refresh_token', 'client_id'     => MELI_CLIENT_ID,
            'client_secret' => MELI_CLIENT_SECRET, 'refresh_token' => $token_data['refresh_token']
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $new_token_data = json_decode($response, true);
        if (isset($new_token_data['access_token'])) {
            $new_token_data['expires_at'] = time() + $new_token_data['expires_in'];
            file_put_contents($token_file_path, json_encode($new_token_data, JSON_PRETTY_PRINT));
            return $new_token_data['access_token'];
        } else {
            die("ERRO CRÍTICO: Falha ao renovar o token de acesso.");
        }
    }
    return $token_data['access_token'];
}

function fazerRequisicaoMeli($url, $token) {
    $ch = curl_init();
    $headers = ['Authorization: Bearer ' . $token,  'api-version: 2', 'Accept: application/json'];
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [ 'body' => json_decode($response, true), 'http_code' => $http_code ];
}

function fazerRequisicaoCurl($url, $postData) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}
?>