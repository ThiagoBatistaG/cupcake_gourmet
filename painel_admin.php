
<?php

session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
require_once "config/conexao.php";

// Verificar se o usuário está conectado e é administrador
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION['usuario_tipo'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

// Consultar quantidade de produtos
$consultaProdutos = $conexao->query(
    "SELECT COUNT(*) FROM produtos WHERE ativo = 1"
);

$totalProdutos = $consultaProdutos->fetchColumn();

// Consultar quantidade de pedidos
$consultaPedidos = $conexao->query(
    "SELECT COUNT(*) FROM pedidos"
);

$totalPedidos = $consultaPedidos->fetchColumn();

// Consultar quantidade de clientes
$consultaClientes = $conexao->query(
    "SELECT COUNT(*) FROM usuarios WHERE tipo = 'cliente'"
);

$totalClientes = $consultaClientes->fetchColumn();

// Consultar pedidos recentes
$sql = "SELECT
            p.id,
            p.data_pedido,
            p.valor_total,
            p.status,
            u.nome AS cliente
        FROM pedidos p
        INNER JOIN usuarios u ON u.id = p.usuario_id
        ORDER BY p.id DESC
        LIMIT 10";

$consultaRecentes = $conexao->query($sql);

$pedidosRecentes = $consultaRecentes->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Painel Administrativo - Cupcake Gourmet</title>

    <link rel="stylesheet" href="public/css/estilo.css">

    <style>

        .painel {
            max-width: 1100px;
            margin: 35px auto;
            padding: 20px;
        }

        .resumo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }

        .cartao-resumo {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.07);
        }

        .cartao-resumo h2 {
            color: #a34f61;
            font-size: 30px;
            margin: 10px 0;
        }

        .area-admin {
            background: white;
            padding: 22px;
            border-radius: 12px;
            margin: 25px 0;
        }

        .tabela-responsiva {
            overflow-x: auto;
        }

        .tabela-responsiva table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .tabela-responsiva th,
        .tabela-responsiva td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .tabela-responsiva th {
            background: #fcebe7;
        }

        .acoes-admin {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }

        .acoes-admin a {
            display: inline-block;
            padding: 12px 18px;
            background: #a34f61;
            color: white;
            text-decoration: none;
            border-radius: 7px;
        }

    </style>

</head>

<body>

<header>

    <div class="logo">Cupcake Gourmet</div>

    <nav>
        <a href="index.php">Ver loja</a>
        <a href="sair.php">Sair</a>
    </nav>

</header>

<main class="painel">

    <h1>Painel administrativo</h1>

    <p>
        Bem-vindo(a),
        <?= htmlspecialchars($_SESSION['usuario_nome']) ?>!
    </p>

    <p>
        Aqui você pode acompanhar os dados da loja e administrar suas operações.
    </p>

    <section class="resumo">

        <div class="cartao-resumo">

            <p>Produtos ativos</p>

            <h2><?= (int) $totalProdutos ?></h2>

        </div>

        <div class="cartao-resumo">

            <p>Pedidos registrados</p>

            <h2><?= (int) $totalPedidos ?></h2>

        </div>

        <div class="cartao-resumo">

            <p>Clientes cadastrados</p>

            <h2><?= (int) $totalClientes ?></h2>

        </div>

    </section>

    <section class="area-admin">

        <h2>Gerenciamento da loja</h2>

        <p>
            Nesta área serão disponibilizadas as ferramentas administrativas.
        </p>

        <div class="acoes-admin">

            <a href="gerenciar_produtos.php">
    Gerenciar produtos
</a>

<a href="gerenciar_pedidos.php">
    Gerenciar pedidos e entregas
</a>

        </div>

    </section>

    <section class="area-admin">

        <h2>Pedidos recentes</h2>

        <?php if (empty($pedidosRecentes)): ?>

            <p>Nenhum pedido registrado.</p>

        <?php else: ?>

            <div class="tabela-responsiva">

                <table>

                    <thead>

                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Valor</th>
                            <th>Status</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($pedidosRecentes as $pedido): ?>

                            <tr>

                                <td>
                                    #<?= (int) $pedido['id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($pedido['cliente']) ?>
                                </td>

                                <td>
                                    <?= date(
                                        'd/m/Y H:i',
                                        strtotime($pedido['data_pedido'])
                                    ) ?>
                                </td>

                                <td>
                                    R$ <?= number_format(
                                        $pedido['valor_total'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($pedido['status']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer>

    <p>Cupcake Gourmet - Área administrativa.</p>

</footer>

</body>
</html>
