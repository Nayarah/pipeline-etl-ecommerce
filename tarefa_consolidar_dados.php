<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(1800);
require_once 'config.php';

// Função auxiliar para agregar dados de vendas (calcula os totais de uma lista de vendas)
function agregar_vendas($vendas_da_variacao, $custo_unit) {
    $agregados = ['qtd' => 0, 'faturamento' => 0.0, 'tarifa' => 0.0, 'frete' => 0.0, 'liquido' => 0.0, 'custo_total' => 0.0];
    if (empty($vendas_da_variacao)) return $agregados;
    foreach ($vendas_da_variacao as $venda) {
        $qtd = (int)$venda['qtd_vendida'];
        $agregados['qtd'] += $qtd;
        $agregados['faturamento'] += (float)$venda['faturamento_bruto_item'];
        $agregados['tarifa'] += (float)$venda['tarifa_ml'];
        $agregados['frete'] += (float)$venda['custo_frete_rateado'];
        $agregados['liquido'] += (float)$venda['liquido_recebido'];
        $agregados['custo_total'] += $custo_unit * $qtd;
    }
    // --- INÍCIO DA CORREÇÃO (LINHA DE DEBUG) ---
    // Imprime na tela a soma final antes de retornar
    echo "Agregação de Vendas - Qtd: " . $agregados['qtd'] . ", Faturamento: " . $agregados['faturamento'] . "<br>";
    // --- FIM DA CORREÇÃO ---
    return $agregados;
}

echo "Iniciando tarefa de CONSOLIDAÇÃO DE DADOS (vNOVA - Lógica de Mapa)...\n<br>";

$data_alvo = date('Y-m-d', strtotime('-3 days'));
echo "Consolidando dados para a data: <b>$data_alvo</b>\n<br>";


/*
// Defina o mês e o ano que você quer reprocessar
$ano = 2025;
$mes = 1;  


$dias_no_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);

// Laço principal que vai rodar o processo para cada dia do mês
for ($dia = 1; $dia <= $dias_no_mes; $dia++) {
    // Monta a data alvo para a iteração atual do loop
    $data_alvo = sprintf("%d-%02d-%02d", $ano, $mes, $dia);

    echo "<hr><h2>Processando dados para a data: <b>$data_alvo</b></h2>";
    
    // TODO: O RESTO DO SEU CÓDIGO VIRÁ AQUI DENTRO
*/ 

// --- ETAPA 1: Carregar todos os dados de referência e transacionais ---
echo "<hr><b>Etapa 1: Carregando todos os dados...</b><br>";

// Carrega o MAPA e o CATÁLOGO para a memória
$mapa_anuncios = [];
$resultado_mapa = $mysqli->query("SELECT id_anuncio_canal, id_anuncio_pai, sku, categoria, titulo_produto, plataforma FROM mapa_produtos_anuncios");
while ($linha = $resultado_mapa->fetch_assoc()) {
    $mapa_anuncios[$linha['id_anuncio_canal']] = $linha;
    echo "A " . $linha['id_anuncio_canal'] . "<br>";
    
}
$resultado_mapa->free();

// Carrega dados de CUSTO do catálogo, indexados por SKU
$catalogo_custos = [];
$resultado_catalogo = $mysqli->query("SELECT sku, custo_produto FROM produtos_catalogo");
while ($linha = $resultado_catalogo->fetch_assoc()) {
    $catalogo_custos[$linha['sku']] = $linha['custo_produto'];
   
}
$resultado_catalogo->free();

// Carrega dados de TRÁFEGO, indexados por ID do ANÚNCIO PAI
$dados_trafego = [];
$stmt_trafego = $mysqli->prepare("SELECT * FROM trafego_diario WHERE data_metrica = ?");
$stmt_trafego->bind_param("s", $data_alvo);
$stmt_trafego->execute();
$resultado_trafego = $stmt_trafego->get_result();
while ($linha = $resultado_trafego->fetch_assoc()) {
    $dados_trafego[$linha['id_anuncio']] = $linha;

}
$stmt_trafego->close();

// Carrega dados de VENDAS, agrupados por ID do ANÚNCIO/VARIAÇÃO
$dados_vendas = [];
$stmt_vendas = $mysqli->prepare("SELECT * FROM vendas_financeiro WHERE DATE(data_venda) = ?");
$stmt_vendas->bind_param("s", $data_alvo);
$stmt_vendas->execute();
$resultado_vendas = $stmt_vendas->get_result();
while ($linha = $resultado_vendas->fetch_assoc()) {
    // Lógica corrigida: usa o id_variacao se existir, senão, usa o id_anuncio
    $id_para_agrupar = !empty($linha['id_variacao']) ? $linha['id_variacao'] : $linha['id_anuncio'];
    $dados_vendas[$id_para_agrupar][] = $linha;
    
}
$stmt_vendas->close();

echo "Todos os dados carregados.<br>";

// --- ETAPA 2: Construir a lista de todas as variações que tiveram atividade ---
$variacoes_a_processar = [];
// Adiciona variações que tiveram vendas
foreach ($dados_vendas as $id_variacao => $vendas) {
    $variacoes_a_processar[$id_variacao] = true;
}
// Adiciona variações que tiveram tráfego (através do pai)
foreach ($dados_trafego as $id_anuncio_pai => $trafego) {
    // Precisamos encontrar todas as variações deste pai no nosso mapa
    foreach($mapa_anuncios as $id_canal => $mapa_info) {
        if ($mapa_info['id_anuncio_pai'] == $id_anuncio_pai) {
            $variacoes_a_processar[$id_canal] = true;
        }
    }
}
echo "Total de " . count($variacoes_a_processar) . " variações únicas para processar.<br>";

// --- ETAPA 2.5: PRÉ-CÁLCULO DE FATURAMENTO TOTAL POR ANÚNCIO-PAI ---
echo "Etapa 2.5: Calculando faturamento total por anúncio-pai para alocação de tráfego...<br>";
$faturamento_total_por_pai = [];

// Itera sobre as vendas, que já estão agrupadas por variação
foreach ($dados_vendas as $id_variacao_venda => $vendas) {
    // Descobre quem é o pai desta variação
    $id_pai_mapa = $mapa_anuncios[$id_variacao_venda]['id_anuncio_pai'] ?? null;
    
    if ($id_pai_mapa) {
        // Inicializa o contador do pai, se for a primeira vez que o vemos
        if (!isset($faturamento_total_por_pai[$id_pai_mapa])) {
            $faturamento_total_por_pai[$id_pai_mapa] = 0.0;
        }
        
        // Soma o faturamento de cada venda individual ao total do pai
        foreach ($vendas as $venda) {
            $faturamento_total_por_pai[$id_pai_mapa] += (float)$venda['faturamento_bruto_item'];
        }
    }
}


// --- ETAPA 3: Consolidação e Inserção ---
echo "<hr><b>Etapa 3: Consolidando e inserindo dados por variação...</b><br>";
// Query de inserção ATUALIZADA com as novas colunas
$stmt_insert = $mysqli->prepare("INSERT INTO relatorio_diario (data_relatorio, id_anuncio, id_anuncio_variacao, sku, categoria_anuncio, titulo_anuncio, impressoes_ads, cliques_ads, custo_ads, vendas_ads_qtd, faturamento_ads, vendas_totais_qtd, faturamento_total, tarifa_venda_total, custo_frete_total, liquido_recebido_total, custo_produto_total, visitas_totais) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
ON DUPLICATE KEY UPDATE id_anuncio=VALUES(id_anuncio), sku=VALUES(sku), categoria_anuncio=VALUES(categoria_anuncio), titulo_anuncio=VALUES(titulo_anuncio), impressoes_ads=VALUES(impressoes_ads), 
cliques_ads=VALUES(cliques_ads), custo_ads=VALUES(custo_ads), vendas_ads_qtd=VALUES(vendas_ads_qtd), faturamento_ads=VALUES(faturamento_ads), 
vendas_totais_qtd=VALUES(vendas_totais_qtd), faturamento_total=VALUES(faturamento_total), tarifa_venda_total=VALUES(tarifa_venda_total), 
custo_frete_total=VALUES(custo_frete_total), liquido_recebido_total=VALUES(liquido_recebido_total), custo_produto_total=VALUES(custo_produto_total), 
visitas_totais=VALUES(visitas_totais)");



foreach (array_keys($variacoes_a_processar) as $id_variacao) {
    // Pega informações do mapa
    $info_mapa = $mapa_anuncios[$id_variacao] ?? null;
    if (!$info_mapa) continue; // Pula se a variação não estiver no mapa
    
    $id_anuncio_pai = $info_mapa['id_anuncio_pai'];
    $sku = $info_mapa['sku'];
    $titulo = $info_mapa['titulo_produto'];
    $categoria = $info_mapa['categoria'];

 /*   // Pega dados de tráfego (do anúncio pai)
    $trafego = $dados_trafego[$id_anuncio_pai] ?? [];
    $impressoes_ads = (int)($trafego['impressoes_ads'] ?? 0);
    $cliques_ads = (int)($trafego['cliques_ads'] ?? 0);
    $custo_ads = (float)($trafego['custo_ads'] ?? 0.00);
    $vendas_ads_qtd = (int)($trafego['vendas_ads_qtd'] ?? 0);
    $faturamento_ads = (float)($trafego['faturamento_total_ads'] ?? 0.00);
    $visitas_totais = (int)($trafego['visitas_totais'] ?? 0);
*/

    // Pega dados de vendas (da variação específica)
    $vendas_da_variacao = $dados_vendas[$id_variacao] ?? [];
    $custo_produto = (float)($catalogo_custos[$sku] ?? 0.00);
    $financeiro_agg = agregar_vendas($vendas_da_variacao, $custo_produto);
    
    
    // --- LÓGICA DE ALOCAÇÃO DE TRÁFEGO (NOVA) ---
    // Pega os dados de tráfego BRUTOS do anúncio-pai
    $trafego_bruto_pai = $dados_trafego[$id_anuncio_pai] ?? null;

    // Inicializa as métricas de tráfego alocadas para esta variação
    $impressoes_ads = 0;
    $cliques_ads = 0;
    $custo_ads = 0.0;
    $vendas_ads_qtd = 0;
    $faturamento_ads = 0.0;
    $visitas_totais = 0;

    if ($trafego_bruto_pai) {
        // Pega o faturamento total do pai, que foi pré-calculado na Etapa 2.5
        $faturamento_pai = $faturamento_total_por_pai[$id_anuncio_pai] ?? 0.0;

        if ($faturamento_pai > 0) {
            // CASO 1: Anúncio teve vendas. Alocar proporcionalmente.
          // ... dentro do if ($faturamento_pai > 0) ...
            $faturamento_desta_variacao = $financeiro_agg['faturamento'];
            $peso = $faturamento_desta_variacao / $faturamento_pai;

            $impressoes_ads = round($trafego_bruto_pai['impressoes_ads'] * $peso);
            $cliques_ads = round($trafego_bruto_pai['cliques_ads'] * $peso);
            $custo_ads = $trafego_bruto_pai['custo_ads'] * $peso;
            $visitas_totais = round($trafego_bruto_pai['visitas_totais'] * $peso);
            
            // --- INÍCIO DA CORREÇÃO PARA VENDAS NEGATIVAS ---
            // Pega o total de vendas desta variação, que já calculamos
            $vendas_totais_desta_variacao = $financeiro_agg['qtd'];
            
            // Calcula a alocação bruta de vendas de ads
            $vendas_ads_alocadas_bruto = $trafego_bruto_pai['vendas_ads_qtd'] * $peso;
            
            // A quantidade de vendas de ads não pode ser maior que as vendas totais da variação.
            // Usamos a função min() para pegar o menor dos dois valores.
            $vendas_ads_qtd = round(min($vendas_ads_alocadas_bruto, $vendas_totais_desta_variacao));
            // --- FIM DA CORREÇÃO ---

            $faturamento_ads = $trafego_bruto_pai['faturamento_total_ads'] * $peso;
// ...
            
        } else {
            // CASO 2: Anúncio teve tráfego mas não vendeu. 
            // Atribui 100% dos dados para a "variação principal" (aquela cujo ID é igual ao do pai).
            // As outras variações deste pai ficarão com os valores de tráfego zerados.
            if ($id_variacao == $id_anuncio_pai) {
                $impressoes_ads = (int)($trafego_bruto_pai['impressoes_ads'] ?? 0);
                $cliques_ads = (int)($trafego_bruto_pai['cliques_ads'] ?? 0);
                $custo_ads = (float)($trafego_bruto_pai['custo_ads'] ?? 0.00);
                $vendas_ads_qtd = (int)($trafego_bruto_pai['vendas_ads_qtd'] ?? 0);
                $faturamento_ads = (float)($trafego_bruto_pai['faturamento_total_ads'] ?? 0.00);
                $visitas_totais = (int)($trafego_bruto_pai['visitas_totais'] ?? 0);
            }
        }
    }
    
   
    // Bind e Execução
    // bind_param com a sequência de tipos de dados CORRETA
$stmt_insert->bind_param("ssssssiidididddddi",
    $data_alvo, $id_anuncio_pai, $id_variacao, $sku, $categoria, $titulo,
    $impressoes_ads,         // i
    $cliques_ads,            // i
    $custo_ads,              // d (corrigido de i)
    $vendas_ads_qtd,         // i (corrigido de d)
    $faturamento_ads,        // d
    $financeiro_agg['qtd'],  // i (corrigido de d)
    $financeiro_agg['faturamento'], // d
    $financeiro_agg['tarifa'],      // d
    $financeiro_agg['frete'],       // d
    $financeiro_agg['liquido'],     // d
    $financeiro_agg['custo_total'], // d
    $visitas_totais,         // i
   
);
    
    $stmt_insert->execute();
}

//} // Fim do laço principal


echo "Consolidação concluída.<br>";
$stmt_insert->close();
$mysqli->close();

echo "<br>✅ Tarefa de CONSOLIDAÇÃO DE DADOS (por Variação) concluída!";
?>