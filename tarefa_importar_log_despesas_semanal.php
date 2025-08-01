<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(1800); // 30 minutos
require_once 'config.php';

echo "Iniciando tarefa de importação de LOG de despesas do TinyERP...\n<br>";


// Define o período (últimos 7 dias)
$data_fim_obj = new DateTime();
$data_inicio_obj = (new DateTime())->modify('-6 days');

/*
// --- ⬇️ CONFIGURE O PERÍODO HISTÓRICO A SER BUSCADO AQUI ⬇️ ---
$data_inicio_str = '2025-07-20';
$data_fim_str = '2025-07-20';
// -----------------------------------------------------

// Converte as strings de data em objetos DateTime para usar no filtro
$data_inicio_obj = new DateTime($data_inicio_str);
$data_fim_obj = new DateTime($data_fim_str);
*/
echo "Buscando contas com VENCIMENTO no período de <b>" . $data_inicio_obj->format('d/m/Y') . "</b> a <b>" . $data_fim_obj->format('d/m/Y') . "</b>...\n<br>";

$pagina_atual = 1;
$hasMorePages = true;
$contas_inseridas = 0;

// --- INÍCIO DA ALTERAÇÃO 1: CORREÇÃO DA QUERY SQL ---
// Prepara o comando SQL com os nomes corretos das colunas da sua tabela.
$stmt = $mysqli->prepare("
    INSERT INTO log_despesas_erp (id_lancamento_tiny, data_vencimento, valor, categoria_mapeada, historico, fornecedor)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        data_vencimento = VALUES(data_vencimento), 
        valor = VALUES(valor), 
        categoria_mapeada = VALUES(categoria_mapeada),
        historico = VALUES(historico), 
        fornecedor = VALUES(fornecedor)
");
// --- FIM DA ALTERAÇÃO 1 ---

// Laço único e paginado para buscar TODAS as contas do período
while($hasMorePages) {
    echo "<hr>Buscando página $pagina_atual de contas a pagar...\n<br>";
    
    // Etapa A: Pesquisar para obter a lista de contas da página
    $postDataPesquisa = http_build_query([
        'token' => TINY_API_TOKEN,
        'formato' => 'json',
        'data_ini_vencimento' => $data_inicio_obj->format('d/m/Y'),
        'data_fim_vencimento' => $data_fim_obj->format('d/m/Y'),
        'pagina' => $pagina_atual,
        'situacao' => 'pago'
    ]);
    
    $responsePesquisa = fazerRequisicaoCurl('https://api.tiny.com.br/api2/contas.pagar.pesquisa.php', $postDataPesquisa);
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
    die("Nenhuma conta com vencimento no período encontrada. Fim.");
}


foreach($all_conta_ids as $id_conta) {

            // Etapa B: Usar o ID para obter os detalhes completos da conta (como no script antigo)
            $postDataDetalhe = http_build_query(['token' => TINY_API_TOKEN, 'formato' => 'json', 'id' => $id_conta]);
            $responseDetalhe = fazerRequisicaoCurl('https://api.tiny.com.br/api2/conta.pagar.obter.php', $postDataDetalhe);
            $dataDetalhe = json_decode($responseDetalhe, true);

            if (isset($dataDetalhe['retorno']['status']) && $dataDetalhe['retorno']['status'] == 'OK' && !empty($dataDetalhe['retorno']['conta'])) {
                $conta = $dataDetalhe['retorno']['conta'];

                // Extrai os dados da resposta DETALHADA
                $id_lancamento = $conta['id'];
                $data_vencimento = date('Y-m-d', strtotime(str_replace('/', '-', $conta['vencimento'])));
                $valor = (float)$conta['valor'];
                $categoria_mapeada = $conta['categoria'] ?? 'Sem Categoria';
                $historico = $conta['historico'] ?? $conta['descricao'] ?? null;
                $fornecedor = $conta['cliente']['nome'] ?? null;

                // Insere/Atualiza no banco de dados
                $stmt->bind_param("isdsss", $id_lancamento, $data_vencimento, $valor, $categoria_mapeada, $historico, $fornecedor);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $contas_inseridas++;
                }
            }
             sleep(1); // Pausa dentro do loop de detalhes
    }

echo "<hr>Tarefa concluída. Total de $contas_inseridas contas inseridas/atualizadas.";
$stmt->close();
$mysqli->close();
?>