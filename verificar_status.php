
<?php

require_once "config/conexao.php";

$tabelas = ["pedidos", "entregas", "pagamentos"];

foreach ($tabelas as $tabela) {

    $sql = "SHOW COLUMNS FROM `$tabela` LIKE 'status'";

    $resultado = $conexao->query($sql)->fetch(PDO::FETCH_ASSOC);

    echo "<h3>Tabela: $tabela</h3>";

    echo "<pre>";
    echo $resultado["Type"];
    echo "</pre>";
}
?>
