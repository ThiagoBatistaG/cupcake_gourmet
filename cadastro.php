
<?php

session_start();

require_once "config/conexao.php";

// Criar token de segurança CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar token CSRF
    $tokenRecebido = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecebido) ||
        !hash_equals($_SESSION['csrf_token'], $tokenRecebido)
    ) {
        http_response_code(403);
        exit("Solicitação inválida. Atualize a página e tente novamente.");
    }

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if (!is_string($nome) || !is_string($email) ||
        !is_string($telefone) || !is_string($senha) ||
        !is_string($confirmar_senha)) {

        $mensagem = "Dados inválidos.";

    } elseif ($nome === '' || $email === '' || $senha === '') {

        $mensagem = "Preencha os campos obrigatórios.";

    } elseif (strlen($nome) > 100) {

        $mensagem = "O nome deve ter no máximo 100 caracteres.";

    } elseif (strlen($email) > 150) {

        $mensagem = "O e-mail deve ter no máximo 150 caracteres.";

    } elseif (strlen($telefone) > 20) {

        $mensagem = "O telefone deve ter no máximo 20 caracteres.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $mensagem = "Digite um e-mail válido.";

    } elseif (strlen($senha) < 8) {

        $mensagem = "A senha precisa ter pelo menos 8 caracteres.";

    } elseif ($senha !== $confirmar_senha) {

        $mensagem = "As senhas não coincidem.";

    } else {

        $consulta = $conexao->prepare(
            "SELECT id FROM usuarios WHERE email = ?"
        );

        $consulta->execute([$email]);

        if ($consulta->fetch()) {

            $mensagem = "Este e-mail já está cadastrado.";

        } else {

            $senha_segura = password_hash(
                $senha,
                PASSWORD_DEFAULT
            );

            $sql = "INSERT INTO usuarios
                    (nome, email, senha, telefone, tipo)
                    VALUES (?, ?, ?, ?, 'cliente')";

            $inserir = $conexao->prepare($sql);

            $inserir->execute([
                $nome,
                $email,
                $senha_segura,
                $telefone
            ]);

            header("Location: login.php?cadastro=sucesso");
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cadastro - Cupcake Gourmet</title>

    <link rel="stylesheet" href="public/css/estilo.css">
</head>

<body>

<header>
    <div class="logo">Cupcake Gourmet</div>

    <nav>
        <a href="index.php">Início</a>
        <a href="login.php">Entrar</a>
    </nav>
</header>

<main class="formulario">

    <h1>Criar minha conta</h1>

    <?php if ($mensagem !== ''): ?>
        <p class="mensagem">
            <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
        >

        <label>Nome completo *</label>
        <input
            type="text"
            name="nome"
            maxlength="100"
            required
            value="<?= htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >

        <label>E-mail *</label>
        <input
            type="email"
            name="email"
            maxlength="150"
            required
            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >

        <label>Telefone</label>
        <input
            type="tel"
            name="telefone"
            maxlength="20"
            value="<?= htmlspecialchars($_POST['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        >

        <label>Senha *</label>
        <input
            type="password"
            name="senha"
            minlength="8"
            required
        >

        <label>Confirmar senha *</label>
        <input
            type="password"
            name="confirmar_senha"
            minlength="8"
            required
        >

        <button type="submit" class="botao">
            Criar conta
        </button>

    </form>

    <p style="text-align:center; margin-top:20px;">
        Já possui uma conta?
        <a href="login.php">Faça login</a>
    </p>

</main>

<footer>
    <p>Cupcake Gourmet - Todos os direitos reservados.</p>
</footer>

</body>
</html>
