
<?php

session_start();

require_once "config/conexao.php";

// Verificar se o usuário está conectado e é administrador
if (
    !isset($_SESSION['usuario_id']) ||
    !isset($_SESSION['usuario_tipo']) ||
    $_SESSION['usuario_tipo'] !== 'admin'
) {
    header("Location: login.php");
    exit;
}

// Criar token de segurança
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensagem = "";
$erro = "";

// Status permitidos no banco
$statusPedidos = [
    "aguardando",
    "confirmado",
    "preparando",
    "enviado",
    "entregue",
    "cancelado"
];

$statusEntregas = [
    "aguardando",
    "em_preparacao",
    "saiu_para_entrega",
    "entregue"
];

$statusPagamentos = [
    "pendente",
    "aprovado",
    "recusado"
];

// Atualizar informações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Solicitação inválida. Atualize a página.");
        }

        $pedidoId = filter_input(INPUT_POST, 'pedido_id', FILTER_VALIDATE_INT);

        $statusPedido = $_POST['status_pedido'] ?? '';
        $statusEntrega = $_POST['status_entrega'] ?? '';
        $statusPagamento = $_POST['status_pagamento'] ?? '';

        if (!$pedidoId) {
            throw new Exception("Pedido inválido.");
        }

        if (!in_array($statusPedido, $statusPedidos, true)) {
            throw new Exception("Status do pedido inválido.");
        }

        if (!in_array($statusEntrega, $statusEntregas, true)) {
            throw new Exception("Status da entrega inválido.");
        }

        if (!in_array($statusPagamento, $statusPagamentos, true)) {
            throw new Exception("Status do pagamento inválido.");
        }

        $conexao->beginTransaction();

        // Verificar se o pedido existe
      
$consulta = $conexao->prepare(
    "SELECT id, status FROM pedidos WHERE id = ? FOR UPDATE"
);

$consulta->execute([$pedidoId]);

$pedidoAnterior = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$pedidoAnterior) {
    throw new Exception("Pedido não encontrado.");
}


        // Atualizar pedido
        $consulta = $conexao->prepare(
            "UPDATE pedidos SET status = ? WHERE id = ?"
        );

        $consulta->execute([$statusPedido, $pedidoId]);

        // Atualizar entrega
        $consulta = $conexao->prepare(
            "UPDATE entregas SET status = ? WHERE pedido_id = ?"
        );

        $consulta->execute([$statusEntrega, $pedidoId]);

        // Registrar data quando a entrega for concluída
        if ($statusEntrega === 'entregue') {

            $consulta = $conexao->prepare(
                "UPDATE entregas
                 SET data_entrega = NOW()
                 WHERE pedido_id = ?"
            );

            $consulta->execute([$pedidoId]);
        }

        // Atualizar pagamento
        $consulta = $conexao->prepare(
            "UPDATE pagamentos SET status = ? WHERE pedido_id = ?"
        );

        $consulta->execute([$statusPagamento, $pedidoId]);

// Gerar notificação quando o status do pedido mudar
if ($pedidoAnterior['status'] !== $statusPedido) {

    $mensagens = [
        'aguardando' => 'Seu pedido foi recebido e está aguardando confirmação.',
        'confirmado' => 'Seu pedido foi confirmado!',
        'preparando' => 'Seu pedido está sendo preparado.',
        'enviado' => 'Seu pedido saiu para entrega!',
        'entregue' => 'Seu pedido foi entregue. Aproveite!',
        'cancelado' => 'Seu pedido foi cancelado.'
    ];

    $mensagemNotificacao = $mensagens[$statusPedido];

    $consulta = $conexao->prepare(
        "INSERT INTO notificacoes
        (usuario_id, pedido_id, mensagem)
        VALUES (?, ?, ?)"
    );

    $consulta->execute([
        $pedidoAnterior['id']
            ? $conexao->query(
                "SELECT usuario_id FROM pedidos WHERE id = " . (int)$pedidoId
              )->fetchColumn()
            : null,
        $pedidoId,
        $mensagemNotificacao
    ]);
}

        $conexao->commit();

        $mensagem = "Pedido #$pedidoId atualizado com sucesso!";

    } catch (Throwable $e) {

        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }

        $erro = $e->getMessage();
    }
}

// Buscar pedidos
$sql = "
SELECT
    p.id,
    p.usuario_id,
    p.data_pedido,
    p.valor_total,
    p.status AS status_pedido,
    u.nome AS nome_cliente,
    u.email,
    e.endereco_entrega,
    e.status AS status_entrega,
    pg.forma_pagamento,
    pg.status AS status_pagamento,
    pg.valor AS valor_pagamento

FROM pedidos p

INNER JOIN usuarios u
    ON p.usuario_id = u.id

LEFT JOIN entregas e
    ON p.id = e.pedido_id

LEFT JOIN pagamentos pg
    ON p.id = pg.pedido_id

ORDER BY p.data_pedido DESC
";

$pedidos = $conexao->query($sql)->fetchAll(PDO::FETCH_ASSOC);

function escapar($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Gerenciar Pedidos - Cupcake Gourmet</title>

<link rel="stylesheet" href="public/css/estilo.css">

<style>

.pedidos-container {
    max-width: 1100px;
    margin: 30px auto;
    padding: 20px;
}

.pedido-card {
    background: white;
    padding: 22px;
    margin-bottom: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
}

.pedido-card h2 {
    color: #9b466c;
}

.pedido-info {
    margin: 8px 0;
    line-height: 1.6;
}

.pedido-card label {
    display: block;
    margin-top: 14px;
    font-weight: bold;
}

.pedido-card select {
    width: 100%;
    padding: 11px;
    margin-top: 6px;
    border: 1px solid #ddd;
    border-radius: 7px;
    background: white;
}

.botao-atualizar {
    margin-top: 20px;
    padding: 12px 20px;
    background: #a84d73;
    color: white;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    width: 100%;
}

.botao-atualizar:hover {
    background: #843653;
}

.mensagem {
    background: #d9f3df;
    padding: 14px;
    border-radius: 7px;
    margin-bottom: 20px;
}

.erro {
    background: #f8d7da;
    padding: 14px;
    border-radius: 7px;
    margin-bottom: 20px;
}

</style>

</head>

<body>

<div class="pedidos-container">

<h1>Gerenciamento de Pedidos</h1>

<p>Administre pedidos, pagamentos e entregas da loja.</p>

<?php if ($mensagem): ?>
    <div class="mensagem">
        <?= escapar($mensagem) ?>
    </div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="erro">
        <?= escapar($erro) ?>
    </div>
<?php endif; ?>

<a href="painel_admin.php">Voltar ao painel administrativo</a>

<hr>

<?php if (empty($pedidos)): ?>

    <p>Nenhum pedido foi realizado ainda.</p>

<?php else: ?>

    <?php foreach ($pedidos as $pedido): ?>

        <div class="pedido-card">

            <h2>Pedido #<?= escapar($pedido['id']) ?></h2>

            <div class="pedido-info">

                <strong>Cliente:</strong>
                <?= escapar($pedido['nome_cliente']) ?><br>

                <strong>E-mail:</strong>
                <?= escapar($pedido['email']) ?><br>

                <strong>Data:</strong>
                <?= escapar($pedido['data_pedido']) ?><br>

                <strong>Valor total:</strong>
                R$ <?= number_format((float)$pedido['valor_total'], 2, ',', '.') ?><br>

                <strong>Endereço:</strong>
                <?= escapar($pedido['endereco_entrega'] ?? 'Não informado') ?><br>

                <strong>Forma de pagamento:</strong>
                <?= escapar($pedido['forma_pagamento'] ?? 'Não informado') ?><br>

                <strong>Valor do pagamento:</strong>
                R$ <?= number_format((float)($pedido['valor_pagamento'] ?? 0), 2, ',', '.') ?>

            </div>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapar($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="pedido_id"
                    value="<?= escapar($pedido['id']) ?>"
                >

                <label>Status do pedido:</label>

                <select name="status_pedido" required>

                    <?php foreach ($statusPedidos as $status): ?>

                        <option
                            value="<?= escapar($status) ?>"
                            <?= $pedido['status_pedido'] === $status ? 'selected' : '' ?>
                        >
                            <?= escapar(ucfirst($status)) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <label>Status da entrega:</label>

                <select name="status_entrega" required>

                    <?php foreach ($statusEntregas as $status): ?>

                        <option
                            value="<?= escapar($status) ?>"
                            <?= ($pedido['status_entrega'] ?? '') === $status ? 'selected' : '' ?>
                        >
                            <?= escapar(ucfirst(str_replace('_', ' ', $status))) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <label>Status do pagamento:</label>

                <select name="status_pagamento" required>

                    <?php foreach ($statusPagamentos as $status): ?>

                        <option
                            value="<?= escapar($status) ?>"
                            <?= ($pedido['status_pagamento'] ?? '') === $status ? 'selected' : '' ?>
                        >
                            <?= escapar(ucfirst($status)) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <button type="submit" class="botao-atualizar">
                    Salvar alterações
                </button>

            </form>

        </div>

    <?php endforeach; ?>

<?php endif; ?>

</div>

</body>
</html>
