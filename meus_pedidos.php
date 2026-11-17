
<?php

session_start();

require_once "config/conexao.php";

// Verificar se o cliente está conectado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = (int) $_SESSION['usuario_id'];

// Buscar pedidos do cliente
$sql = "SELECT
            p.id,
            p.data_pedido,
            p.valor_total,
            p.status AS status_pedido,
            e.endereco_entrega,
            e.status AS status_entrega,
            pg.forma_pagamento,
            pg.status AS status_pagamento
        FROM pedidos p
        LEFT JOIN entregas e ON e.pedido_id = p.id
        LEFT JOIN pagamentos pg ON pg.pedido_id = p.id
        WHERE p.usuario_id = ?
        ORDER BY p.id DESC";

$consulta = $conexao->prepare($sql);
$consulta->execute([$usuario_id]);

$pedidos = $consulta->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meus Pedidos - Cupcake Gourmet</title>

    <link rel="stylesheet" href="public/css/estilo.css">

    <style>

        .pagina-pedidos {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }

        .pedido {
            background: white;
            padding: 22px;
            margin-bottom: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.07);
        }

        .pedido-cabecalho {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .pedido h2 {
            color: #a34f61;
        }

        .pedido p {
            margin: 10px 0;
        }

        .status {
            display: inline-block;
            background: #fcebe7;
            color: #783f48;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        .produto-pedido {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .total-pedido {
            text-align: right;
            font-size: 21px;
            font-weight: bold;
            color: #a34f61;
            margin-top: 15px;
        }

        .endereco-pedido {
            background: #faf5f5;
            padding: 12px;
            border-radius: 8px;
        }

        .pedido-vazio {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
        }

    </style>

</head>

<body>

<header>

    <div class="logo">Cupcake Gourmet</div>

    <nav>
        <a href="index.php">Início</a>
        <a href="carrinho.php">Meu carrinho</a>
        <a href="sair.php">Sair</a>
    </nav>

</header>

<main class="pagina-pedidos">

    <h1>Meus pedidos</h1>

    <p>
        Olá,
        <?= htmlspecialchars($_SESSION['usuario_nome']) ?>!
        Confira suas compras e acompanhe suas entregas.
    </p>

    <?php if (empty($pedidos)): ?>

        <div class="pedido-vazio">

            <h2>Você ainda não realizou pedidos.</h2>

            <p>
                Que tal escolher alguns cupcakes?
            </p>

            <a href="index.php" class="botao">
                Ver produtos
            </a>

        </div>

    <?php else: ?>

        <?php foreach ($pedidos as $pedido): ?>

            <section class="pedido">

                <div class="pedido-cabecalho">

                    <h2>
                        Pedido #<?= (int) $pedido['id'] ?>
                    </h2>

                    <span class="status">

                        <?= htmlspecialchars(
                            $pedido['status_entrega'] ?? 'aguardando'
                        ) ?>

                    </span>

                </div>

                <p>
                    <strong>Data:</strong>

                    <?= date(
                        'd/m/Y H:i',
                        strtotime($pedido['data_pedido'])
                    ) ?>
                </p>

                <p>
                    <strong>Pagamento:</strong>

                    <?= htmlspecialchars(
                        $pedido['forma_pagamento'] ?? 'Não informado'
                    ) ?>
                </p>

                <p>
                    <strong>Status do pagamento:</strong>

                    <?= htmlspecialchars(
                        $pedido['status_pagamento'] ?? 'Não informado'
                    ) ?>
                </p>

                <h3>Produtos</h3>

                <?php

                $sqlItens = "SELECT
                                ip.quantidade,
                                ip.preco_unitario,
                                pr.nome
                             FROM itens_pedido ip
                             INNER JOIN produtos pr
                             ON pr.id = ip.produto_id
                             WHERE ip.pedido_id = ?";

                $consultaItens = $conexao->prepare($sqlItens);

                $consultaItens->execute([$pedido['id']]);

                $itensPedido = $consultaItens->fetchAll(
                    PDO::FETCH_ASSOC
                );

                ?>

                <?php foreach ($itensPedido as $item): ?>

                    <div class="produto-pedido">

                        <?= htmlspecialchars($item['nome']) ?>

                        — <?= (int) $item['quantidade'] ?> unidade(s)

                        — R$

                        <?= number_format(
                            $item['preco_unitario'] * $item['quantidade'],
                            2,
                            ',',
                            '.'
                        ) ?>

                    </div>

                <?php endforeach; ?>

                <div class="endereco-pedido">

                    <strong>Endereço de entrega:</strong>

                    <p>
                        <?= htmlspecialchars(
                            $pedido['endereco_entrega'] ?? 'Não informado'
                        ) ?>
                    </p>

                </div>

                <div class="total-pedido">

                    Total: R$

                    <?= number_format(
                        $pedido['valor_total'],
                        2,
                        ',',
                        '.'
                    ) ?>

                </div>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

<footer>

    <p>Cupcake Gourmet - Todos os direitos reservados.</p>

</footer>

</body>
</html>
