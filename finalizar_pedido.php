
<?php

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once "config/conexao.php";

// Verificar se o cliente está conectado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = (int) $_SESSION['usuario_id'];

$mensagemErro = "";
$pedidoConcluido = filter_input(
    INPUT_GET,
    'pedido',
    FILTER_VALIDATE_INT
);

// Finalizar pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tokenRecebido = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecebido) ||
        !hash_equals($_SESSION['csrf_token'], $tokenRecebido)
    ) {
        http_response_code(403);
        exit("Solicitação inválida. Atualize a página e tente novamente.");
    }

    $endereco = trim($_POST['endereco'] ?? '');
    $forma_pagamento = $_POST['forma_pagamento'] ?? '';

    $formasPermitidas = ['PIX', 'CARTAO', 'DINHEIRO'];

   if ($endereco === '') {

    $mensagemErro = "Informe o endereço de entrega.";

} elseif (strlen($endereco) < 10) {

    $mensagemErro = "Informe um endereço completo.";

} elseif (strlen($endereco) > 255) {

    $mensagemErro = "O endereço deve ter no máximo 255 caracteres.";

    } elseif (!in_array($forma_pagamento, $formasPermitidas, true)) {

        $mensagemErro = "Selecione uma forma de pagamento válida.";

    } elseif (empty($_SESSION['carrinho'])) {

        $mensagemErro = "Seu carrinho está vazio.";

    } else {

        try {

            $conexao->beginTransaction();

            $itensPedido = [];
            $totalCentavos = 0;

            // Conferir produtos, preços e estoque
            foreach ($_SESSION['carrinho'] as $id => $quantidade) {

                $id = (int) $id;
                $quantidade = (int) $quantidade;

                if ($id <= 0 || $quantidade <= 0) {
                    throw new Exception("Item inválido no carrinho.");
                }

                $consulta = $conexao->prepare(
                    "SELECT id, nome, preco, estoque
                     FROM produtos
                     WHERE id = ? AND ativo = 1
                     FOR UPDATE"
                );

                $consulta->execute([$id]);

                $produto = $consulta->fetch(PDO::FETCH_ASSOC);

                if (!$produto) {
                    throw new Exception(
                        "Um produto do carrinho não está mais disponível."
                    );
                }

                if ((int) $produto['estoque'] < $quantidade) {
                    throw new Exception(
                        "Estoque insuficiente para: " . $produto['nome']
                    );
                }

                $precoCentavos = (int) round(
                    (float) $produto['preco'] * 100
                );

                $subtotalCentavos = $precoCentavos * $quantidade;

                $totalCentavos += $subtotalCentavos;

                $itensPedido[] = [
                    'id' => $id,
                    'quantidade' => $quantidade,
                    'preco' => $precoCentavos
                ];
            }

            if (empty($itensPedido)) {
                throw new Exception("Não existem produtos válidos no carrinho.");
            }

            // Registrar pedido
            $valorTotal = number_format(
                $totalCentavos / 100,
                2,
                '.',
                ''
            );

            $sqlPedido = "INSERT INTO pedidos
                          (usuario_id, valor_total)
                          VALUES (?, ?)";

            $inserirPedido = $conexao->prepare($sqlPedido);

            $inserirPedido->execute([
                $usuario_id,
                $valorTotal
            ]);

            $pedido_id = (int) $conexao->lastInsertId();

            // Registrar os itens e atualizar estoque
            foreach ($itensPedido as $item) {

                $precoUnitario = number_format(
                    $item['preco'] / 100,
                    2,
                    '.',
                    ''
                );

                $sqlItem = "INSERT INTO itens_pedido
                            (pedido_id, produto_id, quantidade, preco_unitario)
                            VALUES (?, ?, ?, ?)";

                $inserirItem = $conexao->prepare($sqlItem);

                $inserirItem->execute([
                    $pedido_id,
                    $item['id'],
                    $item['quantidade'],
                    $precoUnitario
                ]);

                $atualizarEstoque = $conexao->prepare(
                    "UPDATE produtos
                     SET estoque = estoque - ?
                     WHERE id = ? AND estoque >= ?"
                );

                $atualizarEstoque->execute([
                    $item['quantidade'],
                    $item['id'],
                    $item['quantidade']
                ]);

                if ($atualizarEstoque->rowCount() !== 1) {
                    throw new Exception(
                        "Não foi possível atualizar o estoque."
                    );
                }
            }

            // Registrar pagamento
            $sqlPagamento = "INSERT INTO pagamentos
                             (pedido_id, forma_pagamento, valor)
                             VALUES (?, ?, ?)";

            $inserirPagamento = $conexao->prepare($sqlPagamento);

            $inserirPagamento->execute([
                $pedido_id,
                $forma_pagamento,
                $valorTotal
            ]);

            // Registrar entrega
            $sqlEntrega = "INSERT INTO entregas
                           (pedido_id, endereco_entrega)
                           VALUES (?, ?)";

            $inserirEntrega = $conexao->prepare($sqlEntrega);

            $inserirEntrega->execute([
                $pedido_id,
                $endereco
            ]);

            // Confirmar todas as operações
            $conexao->commit();

            // Limpar carrinho
            $_SESSION['carrinho'] = [];

            // Evitar repetir o pedido ao atualizar a página
            header("Location: finalizar_pedido.php?pedido=" . $pedido_id);
            exit;

        } catch (Throwable $erro) {

            if ($conexao->inTransaction()) {
                $conexao->rollBack();
            }

            error_log($erro->getMessage());

            $mensagemErro = "Não foi possível finalizar o pedido. "
                          . "Verifique os produtos e tente novamente.";
        }
    }
}

// Buscar endereço cadastrado do cliente
$consultaEndereco = $conexao->prepare(
    "SELECT endereco FROM usuarios WHERE id = ?"
);

$consultaEndereco->execute([$usuario_id]);

$dadosUsuario = $consultaEndereco->fetch(PDO::FETCH_ASSOC);

$enderecoCadastrado = $dadosUsuario['endereco'] ?? '';

// Preparar resumo do carrinho
$itens = [];
$totalCentavos = 0;

if (!$pedidoConcluido && !empty($_SESSION['carrinho'])) {

    foreach ($_SESSION['carrinho'] as $id => $quantidade) {

        $consulta = $conexao->prepare(
            "SELECT id, nome, preco, estoque
             FROM produtos
             WHERE id = ? AND ativo = 1"
        );

        $consulta->execute([(int) $id]);

        $produto = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$produto) {
            $mensagemErro = "Um produto não está mais disponível.";
            continue;
        }

        $quantidade = (int) $quantidade;

        if ($quantidade <= 0 || (int) $produto['estoque'] < $quantidade) {
            $mensagemErro = "Estoque insuficiente para: " . $produto['nome'];
            continue;
        }

        $precoCentavos = (int) round(
            (float) $produto['preco'] * 100
        );

        $subtotal = $precoCentavos * $quantidade;

        $totalCentavos += $subtotal;

        $produto['quantidade'] = $quantidade;
        $produto['subtotal'] = $subtotal;

        $itens[] = $produto;
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Finalizar Pedido - Cupcake Gourmet</title>

    <link rel="stylesheet" href="public/css/estilo.css">

    <style>

        .finalizacao {
            max-width: 700px;
            margin: 40px auto;
            padding: 25px;
            background: white;
            border-radius: 12px;
        }

        .finalizacao label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        .finalizacao input,
        .finalizacao textarea {
            width: 100%;
            padding: 12px;
            margin-top: 7px;
            border: 1px solid #ddd;
            border-radius: 7px;
        }

        .resumo-item {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .total-final {
            text-align: right;
            font-size: 23px;
            font-weight: bold;
            color: #a34f61;
            margin: 25px 0;
        }

        .sucesso {
            background: #e7f7ec;
            color: #246b3b;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .erro {
            background: #fcebea;
            color: #a12d2d;
            padding: 15px;
            border-radius: 8px;
        }

        .opcao-pagamento {
            margin: 12px 0;
        }

        .opcao-pagamento input {
            width: auto;
            margin-right: 8px;
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

<main class="finalizacao">

    <?php if ($pedidoConcluido): ?>

        <div class="sucesso">

            <h1>Pedido realizado com sucesso!</h1>

            <p>
                Seu pedido foi registrado no sistema.
            </p>

            <h2>
                Número do pedido: #<?= (int) $pedidoConcluido ?>
            </h2>

            <p>
                Acompanhe as próximas atualizações do seu pedido.
            </p>

            <a href="index.php" class="botao">
                Voltar à loja
            </a>

        </div>

    <?php elseif (empty($itens)): ?>

        <h1>Finalizar pedido</h1>

        <p>Seu carrinho está vazio ou não possui produtos disponíveis.</p>

        <a href="index.php" class="botao">
            Voltar à loja
        </a>

    <?php else: ?>

        <h1>Finalizar pedido</h1>

        <?php if ($mensagemErro !== ''): ?>

            <p class="erro">
                <?= htmlspecialchars($mensagemErro) ?>
            </p>

        <?php endif; ?>

        <h2>Resumo da compra</h2>

        <?php foreach ($itens as $item): ?>

            <div class="resumo-item">

                <span>
                    <?= htmlspecialchars($item['nome']) ?>
                    (<?= $item['quantidade'] ?> unidade(s))
                </span>

                <strong>
                    R$
                    <?= number_format(
                        $item['subtotal'] / 100,
                        2,
                        ',',
                        '.'
                    ) ?>
                </strong>

            </div>

        <?php endforeach; ?>

        <div class="total-final">

            Total: R$
            <?= number_format(
                $totalCentavos / 100,
                2,
                ',',
                '.'
            ) ?>

        </div>

        <form method="POST">
<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
>
            <h2>Endereço de entrega</h2>

            <label>Informe o endereço completo:</label>

            <textarea
                name="endereco"
                rows="3"
                required
            ><?= htmlspecialchars(
                $_POST['endereco'] ?? $enderecoCadastrado
            ) ?></textarea>

            <h2>Forma de pagamento</h2>

            <div class="opcao-pagamento">
                <label>
                    <input
                        type="radio"
                        name="forma_pagamento"
                        value="PIX"
                        required
                    >
                    PIX
                </label>
            </div>

            <div class="opcao-pagamento">
                <label>
                    <input
                        type="radio"
                        name="forma_pagamento"
                        value="CARTAO"
                        required
                    >
                    Cartão
                </label>
            </div>

            <div class="opcao-pagamento">
                <label>
                    <input
                        type="radio"
                        name="forma_pagamento"
                        value="DINHEIRO"
                        required
                    >
                    Dinheiro
                </label>
            </div>

            <button type="submit" class="botao">
                Confirmar pedido
            </button>

        </form>

    <?php endif; ?>

</main>

<footer>

    <p>Cupcake Gourmet - Todos os direitos reservados.</p>

</footer>

</body>
</html>
