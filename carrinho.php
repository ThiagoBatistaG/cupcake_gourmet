
<?php

session_start();

require_once "config/conexao.php";

if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

// Adicionar produto ao carrinho
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'adicionar') {

        $produto_id = filter_input(
            INPUT_POST,
            'produto_id',
            FILTER_VALIDATE_INT
        );

        $quantidade = filter_input(
            INPUT_POST,
            'quantidade',
            FILTER_VALIDATE_INT
        );

        $quantidade = ($quantidade && $quantidade > 0)
            ? $quantidade : 1;

        if ($produto_id) {

            $sql = "SELECT id, estoque FROM produtos
                    WHERE id = ? AND ativo = TRUE";

            $consulta = $conexao->prepare($sql);
            $consulta->execute([$produto_id]);

            $produto = $consulta->fetch(PDO::FETCH_ASSOC);

            if ($produto && $produto['estoque'] > 0) {

                $atual = $_SESSION['carrinho'][$produto_id] ?? 0;

                $nova_quantidade = min(
                    $atual + $quantidade,
                    (int) $produto['estoque']
                );

                $_SESSION['carrinho'][$produto_id] =
                    $nova_quantidade;
            }
        }

        header("Location: carrinho.php");
        exit;
    }

    // Atualizar quantidades
    if ($acao === 'atualizar') {

        foreach ($_POST['quantidades'] ?? [] as $id => $qtd) {

            $id = filter_var($id, FILTER_VALIDATE_INT);
            $qtd = filter_var($qtd, FILTER_VALIDATE_INT);

            if (!$id) {
                continue;
            }

            if (!$qtd || $qtd <= 0) {
                unset($_SESSION['carrinho'][$id]);
                continue;
            }

            $consulta = $conexao->prepare(
                "SELECT estoque FROM produtos
                 WHERE id = ? AND ativo = TRUE"
            );

            $consulta->execute([$id]);
            $produto = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                unset($_SESSION['carrinho'][$id]);
                continue;
            }

            $_SESSION['carrinho'][$id] = min(
                $qtd,
                (int) $produto['estoque']
            );
        }

        header("Location: carrinho.php");
        exit;
    }

    // Remover produto
    if ($acao === 'remover') {

        $id = filter_input(
            INPUT_POST,
            'produto_id',
            FILTER_VALIDATE_INT
        );

        if ($id) {
            unset($_SESSION['carrinho'][$id]);
        }

        header("Location: carrinho.php");
        exit;
    }
}

// Buscar produtos do carrinho
$itens = [];
$total = 0;

foreach ($_SESSION['carrinho'] as $id => $quantidade) {

    $consulta = $conexao->prepare(
        "SELECT * FROM produtos
         WHERE id = ? AND ativo = TRUE"
    );

    $consulta->execute([$id]);
    $produto = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        unset($_SESSION['carrinho'][$id]);
        continue;
    }

    $quantidade = min(
        (int) $quantidade,
        (int) $produto['estoque']
    );

    if ($quantidade <= 0) {
        unset($_SESSION['carrinho'][$id]);
        continue;
    }

    $_SESSION['carrinho'][$id] = $quantidade;

    $subtotal = $quantidade * (float) $produto['preco'];

    $produto['quantidade'] = $quantidade;
    $produto['subtotal'] = $subtotal;

    $itens[] = $produto;
    $total += $subtotal;
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meu Carrinho - Cupcake Gourmet</title>

    <link rel="stylesheet" href="public/css/estilo.css">

    <style>
        .carrinho {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }

        .item-carrinho {
            background: white;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .item-carrinho input {
            width: 65px;
            padding: 8px;
        }

        .total {
            text-align: right;
            font-size: 24px;
            font-weight: bold;
            color: #a34f61;
            margin: 25px 0;
        }

        .acoes {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .botao-remover {
            background: #777;
        }
    </style>
</head>

<body>

<header>
    <div class="logo">Cupcake Gourmet</div>

    <nav>
        <a href="index.php">Início</a>
        <a href="carrinho.php">Meu carrinho</a>
    </nav>
</header>

<main class="carrinho">

    <h1>Meu carrinho de compras</h1>

    <?php if (empty($itens)): ?>

        <p>Seu carrinho está vazio.</p>

        <a href="index.php" class="botao">
            Escolher cupcakes
        </a>

    <?php else: ?>

        <form method="POST" action="carrinho.php">

            <input type="hidden" name="acao" value="atualizar">

            <?php foreach ($itens as $item): ?>

                <div class="item-carrinho">

                    <div>
                        <h3>
                            <?= htmlspecialchars($item['nome']) ?>
                        </h3>

                        <p>
                            Preço unitário:
                            R$ <?= number_format(
                                $item['preco'], 2, ',', '.'
                            ) ?>
                        </p>
<p>
    Disponível em estoque:
    <?= (int) $item['estoque'] ?> unidades
</p>
                        <p>
                            Subtotal:
                            R$ <?= number_format(
                                $item['subtotal'], 2, ',', '.'
                            ) ?>
                        </p>
                    </div>

                    <div>
                        <label>
                            Quantidade:
                            <input
                                type="number"
                                name="quantidades[<?= $item['id'] ?>]"
                                value="<?= $item['quantidade'] ?>"
                                min="0"
                                max="<?= $item['estoque'] ?>"
                            >
                        </label>
                    </div>

                    <button
                        class="botao botao-remover"
                        type="submit"
                        form="remover-<?= $item['id'] ?>"
                    >
                        Remover
                    </button>

                </div>

            <?php endforeach; ?>

            <button type="submit" class="botao">
                Atualizar carrinho
            </button>

        </form>

        <?php foreach ($itens as $item): ?>

            <form
                method="POST"
                action="carrinho.php"
                id="remover-<?= $item['id'] ?>"
            >
                <input type="hidden" name="acao" value="remover">
                <input
                    type="hidden"
                    name="produto_id"
                    value="<?= $item['id'] ?>"
                >
            </form>

        <?php endforeach; ?>

        <div class="total">
            Total: R$ <?= number_format($total, 2, ',', '.') ?>
        </div>
<p>
    <a href="finalizar_pedido.php" class="botao">
        Finalizar compra
    </a>
</p>
        <p>
            <a href="index.php" class="botao">
                Continuar comprando
            </a>
        </p>

    <?php endif; ?>

</main>

<footer>
    <p>Cupcake Gourmet - Todos os direitos reservados.</p>
</footer>

</body>
</html>
