<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(1800); // 30 minutos
require_once 'config.php';

echo "Iniciando tarefa de importação de LOG de recebíveis do TinyERP (v2 - Lógica Detalhada)...\n<br>";


// Define o período (últimos 7 dias)
$data_fim_obj = new DateTime();
$data_inicio_obj = (new DateTime())->modify('-6 days');

/*
// --- ⬇️ CONFIGURE O PERÍODO HISTÓRICO A SER BUSCADO AQUI ⬇️ ---
$data_inicio_str = '2025-07-01';
$data_fim_str = '2025-07-20';
// -----------------------------------------------------

// Converte as strings de data em objetos DateTime para usar no filtro
$data_inicio_obj = new DateTime($data_inicio_str);
$data_fim_obj = new DateTime($data_fim_str);
*/
echo "Buscando contas com RECEBIMENTO no período de <b>" . $data_inicio_obj->format('d/m/Y') . "</b> a <b>" . $data_fim_obj->format('d/m/Y') . "</b>...\n<br>";

// === ETAPA A: BUSCAR TODOS OS IDs DE CONTAS DO PERÍODO ===
$all_conta_ids = [];
$pagina_atual = 1;
$hasMorePages = true;

while($hasMorePages) {
    echo "Buscando página $pagina_atual de contas a receber...\n<br>";
    
    $postDataPesquisa = http_build_query([
        'token' => TINY_API_TOKEN,
        'formato' => 'json',
        'data_ini_vencimento' => $data_inicio_obj->format('d/m/Y'),
        'data_fim_vencimento' => $data_fim_obj->format('d/m/Y'),
        'situacao' => 'pago',
        'pagina' => $pagina_atual
    ]);
    
    $responsePesquisa = fazerRequisicaoCurl('https://api.tiny.com.br/api2/contas.receber.pesquisa.php', $postDataPesquisa);
    $dataPesquisa = json_decode($responsePesquisa, true);

    if (isset($dataPesquisa['retorno']['status']) && $dataPesquisa['retorno']['status'] == 'OK' && !empty($dataPesquisa['retorno']['contas'])) {
        foreach ($dataPesquisa['retorno']['contas'] as $conta_item) {
            $all_conta_ids[] = $conta_item['conta']['id'];
        }
        $pagina_atual++;
    } else {
        $hasMorePages = false;
    }
    sleep(1);
}

if(empty($all_conta_ids)) {
    die("Nenhuma conta recebida no período encontrada. Fim.");
}


// === ETAPA B: BUSCAR OS DETALHES DE CADA CONTA E SALVAR ===
$contas_inseridas = 0;
$stmt = $mysqli->prepare("
    INSERT INTO log_recebiveis_erp (id_lancamento_tiny, data_recebimento, valor_recebido, historico, cliente)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        data_recebimento = VALUES(data_recebimento), 
        valor_recebido = VALUES(valor_recebido), 
        historico = VALUES(historico), 
        cliente = VALUES(cliente)
");

foreach($all_conta_ids as $id_conta) {
    // Usa o endpoint de OBTER para pegar os detalhes completos
    $postDataDetalhe = http_build_query(['token' => TINY_API_TOKEN, 'formato' => 'json', 'id' => $id_conta]);
    $responseDetalhe = fazerRequisicaoCurl('https://api.tiny.com.br/api2/conta.receber.obter.php', $postDataDetalhe);
    $dataDetalhe = json_decode($responseDetalhe, true);

    if (isset($dataDetalhe['retorno']['status']) && $dataDetalhe['retorno']['status'] == 'OK' && !empty($dataDetalhe['retorno']['conta'])) {
        $conta = $dataDetalhe['retorno']['conta'];

        // Extrai os dados da resposta DETALHADA
        $id_lancamento = $conta['id'];
        $data_recebimento = date('Y-m-d', strtotime(str_replace('/', '-', $conta['vencimento'])));
        $valor_recebido = (float)$conta['valor'];
        $historico = $conta['historico'] ?? $conta['descricao'] ?? null;
        $cliente = $conta['cliente']['nome'] ?? null;

        // Insere/Atualiza no banco de dados
        $stmt->bind_param("isdss", $id_lancamento, $data_recebimento, $valor_recebido, $historico, $cliente);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $contas_inseridas++;
        }
    }
    sleep(1); // Pausa para não sobrecarregar a API
}

echo "<hr>Tarefa concluída. Total de $contas_inseridas contas inseridas/atualizadas.";
$stmt->close();
$mysqli->close();
?>