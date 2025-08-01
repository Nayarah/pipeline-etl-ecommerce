<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
require_once 'config.php';

echo "Iniciando: Populando a fila de tarefas de tráfego...\n<br>";

// Define a data para criar as tarefas (D-3, para garantir que os dados de Ads estejam prontos)

$data_alvo = date('Y-m-d', strtotime('-3 days'));

echo "Criando tarefas para a data: <b>$data_alvo</b>\n<br>";

/* 

// Defina aqui as datas exatas que você quer processar
$datas_para_processar = [
    '2025-03-01',
    '2025-03-02'
];

// Laço que vai rodar apenas para as datas que você especificou
foreach ($datas_para_processar as $data_alvo) {
    

    echo "<hr><h2>Processando dados para a data: <b>$data_alvo</b></h2>";
    
*/
    
   // TODO: O RESTO DO SEU CÓDIGO VIRÁ AQUI DENTRO

$anuncios_ativos = [];
$resultado = $mysqli->query("SELECT DISTINCT id_anuncio_pai FROM anuncios_canais WHERE status = 'active'");
while ($linha = $resultado->fetch_assoc()) {
    $anuncios_ativos[] = $linha['id_anuncio_pai'];
}
$resultado->free();

if (empty($anuncios_ativos)) {
    die("Nenhum anúncio ativo encontrado para criar tarefas.");
}

$stmt = $mysqli->prepare("INSERT IGNORE INTO tarefas_pendentes_trafego (id_anuncio, data_metrica) VALUES (?, ?)");
$count = 0;

foreach ($anuncios_ativos as $id_anuncio) {
    $stmt->bind_param("ss", $id_anuncio, $data_alvo);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $count++;
    }
}


echo "✅ Fila populada com $count novas tarefas.";

//}

$stmt->close();
$mysqli->close();
?>