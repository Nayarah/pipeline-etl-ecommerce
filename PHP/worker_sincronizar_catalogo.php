<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(900);
require_once 'config.php';

// --- CONFIGURAÇÃO ---
$batch_size = 50;
// --------------------

echo "================================================\n";
echo "INICIANDO WORKER DE CATÁLOGO (Lote: $batch_size tarefas) - " . date('Y-m-d H:i:s') . "\n";

$mysqli->query("UPDATE tarefas_pendentes_catalogo SET status = 'processando', data_processamento = NOW() WHERE status = 'pendente' LIMIT $batch_size");
$resultado = $mysqli->query("SELECT id, id_produto_tiny FROM tarefas_pendentes_catalogo WHERE status = 'processando'");
$tarefas = $resultado->fetch_all(MYSQLI_ASSOC);
$resultado->free();

if (empty($tarefas)) { die("Nenhuma tarefa de catálogo pendente encontrada. Encerrando.\n"); }
echo count($tarefas) . " tarefas travadas para processamento.\n";

$token = obterTokenMeli();
if (!$token) { die("ERRO CRÍTICO: Não foi possível obter um token de acesso válido."); }



// ...
$stmt_catalogo = $mysqli->prepare("INSERT
    INTO produtos_catalogo (sku, id_produto_tiny, nome_produto, ean, custo_produto)     
    VALUES (?, ?, ?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
    id_produto_tiny=VALUES(id_produto_tiny), 
        nome_produto=VALUES(nome_produto), 
        ean=VALUES(ean), 
        custo_produto=VALUES(custo_produto),
        data_atualizacao=NOW()
");
foreach ($tarefas as $tarefa) {
    $id_tarefa = $tarefa['id'];
    $id_produto_tiny = $tarefa['id_produto_tiny'];
    echo "<hr style='border-top: 1px solid #ccc;'>";
    echo "<b>Processando Tarefa ID: $id_tarefa (Tiny ID: $id_produto_tiny)</b><br>";

    // --- Chamada para a API do Tiny ---
    $postData = http_build_query(['token' => TINY_API_TOKEN, 'formato' => 'json', 'id' => $id_produto_tiny]);
    $response = fazerRequisicaoCurl('https://api.tiny.com.br/api2/produto.obter.php', $postData);
    $data = json_decode($response, true);

    // --- Debug da Resposta da API ---
    echo "<i>Resposta da API Tiny:</i> <pre style='background:#f9f9f9; border:1px solid #eee; padding:5px; font-size:12px;'>";
    print_r($data);
    echo "</pre>";

    if (isset($data['retorno']['status']) && $data['retorno']['status'] == 'OK' && !empty($data['retorno']['produto'])) {
        $produto = $data['retorno']['produto'];
        $sku = $produto['sku'] ?? $produto['codigo'] ?? null;
        echo "<i>SKU encontrado na resposta:</i> '$sku $nome_produto  $ean  $custo_produto'<br>";

        if (empty($sku)) {
            echo "<b style='color:orange;'>AVISO:</b> SKU vazio. Pulando para a próxima tarefa.<br>";
            $mysqli->query("UPDATE tarefas_pendentes_catalogo SET status = 'erro', mensagem_erro = 'Produto sem SKU ou Código no Tiny' WHERE id = " . $id_tarefa);
            sleep(1);
            continue; // Pula para a próxima tarefa no laço
        }

        $nome_produto = $produto['nome'];
        $ean = $produto['gtin'] ?? null;
        $custo_produto = (float)$produto['preco_custo'];

        echo "<i>Preparando para inserir/atualizar SKU '$sku' no banco...</i><br>";

        $stmt_catalogo->bind_param("sissd", $sku, $id_produto_tiny, $nome_produto, $ean, $custo_produto);
        $stmt_catalogo->execute();

        if ($stmt_catalogo->affected_rows > 0) {
            echo "<b style='color:green;'>SUCESSO:</b> Banco de dados atualizado. Linhas afetadas: " . $stmt_catalogo->affected_rows . "<br>";
        } else {
            echo "<b style='color:blue;'>INFO:</b> Nenhuma linha foi alterada no banco de dados (provavelmente os dados já eram os mesmos).$sku, $nome_produto, $ean, $custo_produto<br>";
        }
        
        $mysqli->query("UPDATE tarefas_pendentes_catalogo SET status = 'concluido' WHERE id = " . $id_tarefa);

    } else {
        $mensagem_erro = $mysqli->real_escape_string("Falha na API ou status NOK: " . ($data['retorno']['erros'][0]['erro'] ?? 'Erro desconhecido'));
        echo "<b style='color:red;'>ERRO:</b> A chamada para a API do Tiny falhou. Mensagem: $mensagem_erro <br>";
        $mysqli->query("UPDATE tarefas_pendentes_catalogo SET status = 'erro', mensagem_erro = '$mensagem_erro' WHERE id = " . $id_tarefa);
    }
    sleep(1);
}
// ...



$stmt_catalogo->close();
$mysqli->close();

echo "Lote de tarefas de catálogo processado com sucesso.\n";
echo "================================================\n";
?>