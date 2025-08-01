<?php
// Arquivo: autorizar.php
// Responsabilidade: Iniciar o fluxo de autorização OAuth2.

require_once 'config.php';

$auth_url = "https://auth.mercadolivre.com.br/authorization" .
            "?response_type=code" .
            "&client_id=" . MELI_CLIENT_ID .
            "&redirect_uri=" . MELI_REDIRECT_URI;

// Redireciona o navegador do usuário para a página de autorização do Meli
header('Location: ' . $auth_url);
exit();
?>