
<?php

session_start();

require_once "config/conexao.php";

// Verificar se o cliente está conectado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuarioId = $_SESSION['usuario_id'];

// Marcar notificação como lida
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Solicitação inválida. Atualize a página.");
    }

    $notificacaoId = filter_input(
        INPUT_POST,
        'notificacao_id',
        FILTER_VALIDATE_INT
    );

    if ($notificacaoId) {

        $consulta = $conexao->prepare(
            "UPDATE notificacoes
             SET lida = TRUE
             WHERE id = ? AND usuario_id = ?"
        );

        $consulta->execute([$notificacaoId, $usuarioId]);
    }

    header("Location: notificacoes.php");
    exit;
}

// Criar token de segurança
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Buscar notificações do cliente
$consulta = $conexao->prepare(
    "SELECT
        n.id,
        n.mensagem,
        n.lida,
        n.data_criacao,
        n.pedido_id,
        p.status AS status_pedido
     FROM notificacoes n
     LEFT JOIN pedidos p ON n.pedido_id = p.id
     WHERE n.usuario_id = ?
     ORDER BY n.data_criacao DESC"
);

$consulta->execute([$usuarioId]);

$notificacoes = $consulta->fetchAll(PDO::FETCH_ASSOC);

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Minhas Notificações - Cupcake Gourmet</title>

<link rel="stylesheet" href="public/css/estilo.css">

<style>

.notificacoes-container {
    max-width: 800px;
    margin: 35px auto;
    padding: 20px;
}

.notificacao-card {
    background: white;
    padding: 18px;
    margin-bottom: 15px;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.07);
    border-left: 5px solid #a84d73;
}

.notificacao-lida {
    border-left-color: #aaa;
    opacity: 0.75;
}

.notificacao-card p {
    line-height: 1.5;
}

.data-notificacao {
    color: #777;
    font-size: 13px;
}

.botao-lida {
    padding: 9px 15px;
    background: #a84d73;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.vazio {
    background: white;
    padding: 25px;
    border-radius: 10px;
    text-align: center;
}

</style>

</head>

<body>

<div class="notificacoes-container">

    <h1>Minhas notificações</h1>

    <p>Acompanhe os avisos e atualizações dos seus pedidos.</p>

    <hr>

    <?php if (empty($notificacoes)): ?>

        <div class="vazio">
            <h3>Nenhuma notificação encontrada.</h3>
            <p>Quando houver novidades sobre seus pedidos, elas aparecerão aqui.</p>
        </div>

    <?php else: ?>

        <?php foreach ($notificacoes as $notificacao): ?>

            <div class="notificacao-card <?= $notificacao['lida'] ? 'notificacao-lida' : '' ?>">

                <p>
                    <strong>
                        <?= $notificacao['lida'] ? 'Notificação lida' : 'Nova notificação' ?>
                    </strong>
                </p>

                <p>
                    <?= escapar($notificacao['mensagem']) ?>
                </p>

                <?php if ($notificacao['pedido_id']): ?>

                    <p>
                        Pedido #<?= escapar($notificacao['pedido_id']) ?>
                    </p>

                <?php endif; ?>

                <p class="data-notificacao">
                    <?= date(
                        'd/m/Y H:i',
                        strtotime($notificacao['data_criacao'])
                    ) ?>
                </p>

                <?php if (!$notificacao['lida']): ?>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escapar($_SESSION['csrf_token']) ?>"
                        >

                        <input
                            type="hidden"
                            name="notificacao_id"
                            value="<?= escapar($notificacao['id']) ?>"
                        >

                        <button class="botao-lida" type="submit">
                            Marcar como lida
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

    <p>
        <a href="index.php">← Voltar para a loja</a>
    </p>

</div>

</body>
</html>
