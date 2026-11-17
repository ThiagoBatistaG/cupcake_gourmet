
<?php

session_start();

require_once "config/conexao.php";

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$mensagem = "";
$erro = "";

// Receber produto selecionado
$produtoId = filter_input(INPUT_GET, 'produto_id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $produtoId = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
    $nota = filter_input(INPUT_POST, 'nota', FILTER_VALIDATE_INT);
    $comentario = trim($_POST['comentario'] ?? '');

    try {

        if (!$produtoId || !$nota || $nota < 1 || $nota > 5) {
            throw new Exception("Selecione um produto e uma nota válida.");
        }

        // Verificar se o produto existe
        $consulta = $conexao->prepare(
            "SELECT id FROM produtos WHERE id = ? AND ativo = TRUE"
        );

        $consulta->execute([$produtoId]);

        if (!$consulta->fetch()) {
            throw new Exception("Produto não encontrado.");
        }

        // Verificar se já existe avaliação
        $consulta = $conexao->prepare(
            "SELECT id FROM avaliacoes
             WHERE usuario_id = ? AND produto_id = ?"
        );

        $consulta->execute([$usuarioId, $produtoId]);

        if ($consulta->fetch()) {
            throw new Exception("Você já avaliou este cupcake.");
        }

        // Salvar avaliação
        $consulta = $conexao->prepare(
            "INSERT INTO avaliacoes
            (usuario_id, produto_id, nota, comentario)
            VALUES (?, ?, ?, ?)"
        );

        $consulta->execute([
            $usuarioId,
            $produtoId,
            $nota,
            $comentario
        ]);

        $mensagem = "Sua avaliação foi registrada com sucesso!";

    } catch (PDOException $e) {

        if ($e->getCode() === '23000') {
            $erro = "Você já avaliou este cupcake.";
        } else {
            $erro = "Erro ao salvar a avaliação. Tente novamente.";
        }

    } catch (Exception $e) {

        $erro = $e->getMessage();
    }
}

// Buscar produtos
$produtos = $conexao->query(
    "SELECT id, nome FROM produtos
     WHERE ativo = TRUE ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Avaliar Cupcake - Cupcake Gourmet</title>

<link rel="stylesheet" href="public/css/estilo.css">

<style>

.avaliacao-container {
    max-width: 600px;
    margin: 40px auto;
    padding: 25px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
}

.avaliacao-container h1 {
    color: #a84d73;
    text-align: center;
}

.avaliacao-container label {
    display: block;
    margin-top: 18px;
    font-weight: bold;
}

.avaliacao-container select,
.avaliacao-container textarea {
    width: 100%;
    padding: 12px;
    margin-top: 7px;
    border: 1px solid #ddd;
    border-radius: 7px;
    box-sizing: border-box;
}

.avaliacao-container textarea {
    min-height: 120px;
    resize: vertical;
}

.avaliacao-container button {
    width: 100%;
    padding: 13px;
    margin-top: 22px;
    background: #a84d73;
    color: white;
    border: none;
    border-radius: 7px;
    cursor: pointer;
}

.mensagem {
    background: #d9f3df;
    padding: 12px;
    border-radius: 7px;
}

.erro {
    background: #f8d7da;
    padding: 12px;
    border-radius: 7px;
}

</style>

</head>

<body>

<div class="avaliacao-container">

<h1>Avalie seu cupcake!</h1>

<p>Conte para nós o que achou do produto.</p>

<?php if ($mensagem): ?>
    <p class="mensagem">
        <?= htmlspecialchars($mensagem) ?>
    </p>
<?php endif; ?>

<?php if ($erro): ?>
    <p class="erro">
        <?= htmlspecialchars($erro) ?>
    </p>
<?php endif; ?>

<form method="POST">

    <label>Escolha o cupcake:</label>

    <select name="produto_id" required>

        <option value="">Selecione um produto</option>

        <?php foreach ($produtos as $produto): ?>

            <option
                value="<?= $produto['id'] ?>"
                <?= $produtoId == $produto['id'] ? 'selected' : '' ?>
            >
                <?= htmlspecialchars($produto['nome']) ?>
            </option>

        <?php endforeach; ?>

    </select>

    <label>Sua nota:</label>

    <select name="nota" required>

        <option value="">Escolha uma nota</option>
        <option value="5">★★★★★ - Excelente</option>
        <option value="4">★★★★ - Muito bom</option>
        <option value="3">★★★ - Bom</option>
        <option value="2">★★ - Regular</option>
        <option value="1">★ - Ruim</option>

    </select>

    <label>Comentário:</label>

    <textarea
        name="comentario"
        maxlength="1000"
        placeholder="Conte sua experiência com este cupcake..."
    ></textarea>

    <button type="submit">
        Enviar avaliação
    </button>

</form>

<p>
    <a href="index.php">Voltar para a loja</a>
</p>

</div>

</body>
</html>
