
<?php

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once "config/conexao.php";

$mensagem = "";

if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'sucesso') {
    $mensagem = "Cadastro realizado com sucesso! Faça seu login.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tokenRecebido = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecebido) ||
        !hash_equals($_SESSION['csrf_token'], $tokenRecebido)
    ) {
        http_response_code(403);
        exit("Solicitação inválida. Atualize a página e tente novamente.");
    }

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $consulta = $conexao->prepare(
        "SELECT id, nome, email, senha, tipo
         FROM usuarios
         WHERE email = ?"
    );

    $consulta->execute([$email]);

    $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {

        session_regenerate_id(true);

        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];

        header("Location: index.php");
        exit;

    } else {

        $mensagem = "E-mail ou senha incorretos.";

    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Cupcake Gourmet</title>

    <link rel="stylesheet" href="public/css/estilo.css">
</head>

<body>

<header>
    <div class="logo">Cupcake Gourmet</div>

    <nav>
        <a href="index.php">Início</a>
        <a href="cadastro.php">Criar conta</a>
    </nav>
</header>

<main class="formulario">

    <h1>Entrar na minha conta</h1>

    <?php if ($mensagem !== ''): ?>
        <p class="mensagem">
            <?= htmlspecialchars($mensagem) ?>
        </p>
    <?php endif; ?>

    <form method="POST">
<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
>
        <label>E-mail</label>
        <input type="email" name="email" required>

        <label>Senha</label>
        <input type="password" name="senha" required>

        <button type="submit" class="botao">
            Entrar
        </button>

    </form>

    <p style="text-align:center; margin-top:20px;">
        Ainda não tem conta?
        <a href="cadastro.php">Cadastre-se</a>
    </p>

</main>

<footer>
    <p>Cupcake Gourmet - Todos os direitos reservados.</p>
</footer>

</body>
</html>
