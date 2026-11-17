
<?php

require_once "config/conexao.php";

// Identificar o produto
$produtoId = filter_input(INPUT_GET, 'produto_id', FILTER_VALIDATE_INT);

if (!$produtoId) {
    header("Location: index.php");
    exit;
}

// Buscar produto
$consulta = $conexao->prepare(
    "SELECT id, nome, descricao, preco
     FROM produtos
     WHERE id = ? AND ativo = TRUE"
);

$consulta->execute([$produtoId]);

$produto = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$produto) {
    die("Produto não encontrado.");
}

// Buscar média e quantidade de avaliações
$consulta = $conexao->prepare(
    "SELECT
        COUNT(*) AS quantidade,
        AVG(nota) AS media
     FROM avaliacoes
     WHERE produto_id = ?"
);

$consulta->execute([$produtoId]);

$resumo = $consulta->fetch(PDO::FETCH_ASSOC);

// Buscar comentários
$consulta = $conexao->prepare(
    "SELECT
        a.nota,
        a.comentario,
        a.data_avaliacao,
        u.nome AS nome_cliente
     FROM avaliacoes a
     INNER JOIN usuarios u
        ON a.usuario_id = u.id
     WHERE a.produto_id = ?
     ORDER BY a.data_avaliacao DESC"
);

$consulta->execute([$produtoId]);

$avaliacoes = $consulta->fetchAll(PDO::FETCH_ASSOC);

// Função para proteger textos exibidos
function escapar($valor) {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Avaliações - Cupcake Gourmet</title>

<link rel="stylesheet" href="public/css/estilo.css">

<style>

.avaliacoes-container {
    max-width: 850px;
    margin: 35px auto;
    padding: 20px;
}

.resumo-avaliacoes {
    background: white;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
    margin: 25px 0;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
}

.media-estrelas {
    color: #e5a400;
    font-size: 30px;
    margin: 10px 0;
}

.avaliacao-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 18px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.06);
}

.estrelas {
    color: #e5a400;
    font-size: 22px;
}

.nome-cliente {
    font-weight: bold;
    color: #a84d73;
}

.data-avaliacao {
    color: #777;
    font-size: 13px;
}

.sem-avaliacoes {
    background: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}

.botao-avaliar {
    display: inline-block;
    padding: 12px 20px;
    margin: 15px 0;
    background: #a84d73;
    color: white;
    text-decoration: none;
    border-radius: 7px;
}

</style>

</head>

<body>

<div class="avaliacoes-container">

    <h1>Avaliações dos clientes</h1>

    <h2><?= escapar($produto['nome']) ?></h2>

    <p><?= escapar($produto['descricao']) ?></p>

    <div class="resumo-avaliacoes">

        <h2>Opinião dos clientes</h2>

        <?php if ($resumo['quantidade'] > 0): ?>

            <div class="media-estrelas">

                <?php
                $media = (float)$resumo['media'];
                echo str_repeat("★", (int)round($media));
                echo str_repeat("☆", 5 - (int)round($media));
                ?>

            </div>

            <h2>
                <?= number_format($media, 1, ',', '.') ?> de 5
            </h2>

            <p>
                <?= (int)$resumo['quantidade'] ?>
                avaliação(ões)
            </p>

        <?php else: ?>

            <p>Este cupcake ainda não recebeu avaliações.</p>

        <?php endif; ?>

        <a
            class="botao-avaliar"
            href="avaliar_produto.php?produto_id=<?= $produto['id'] ?>"
        >
            ⭐ Avaliar este cupcake
        </a>

    </div>

    <h2>Comentários dos clientes</h2>

    <?php if (empty($avaliacoes)): ?>

        <div class="sem-avaliacoes">
            Ainda não existem comentários para este produto.
        </div>

    <?php else: ?>

        <?php foreach ($avaliacoes as $avaliacao): ?>

            <div class="avaliacao-card">

                <p class="nome-cliente">
                    <?= escapar($avaliacao['nome_cliente']) ?>
                </p>

                <div class="estrelas">
                    <?php
                    echo str_repeat("★", (int)$avaliacao['nota']);
                    echo str_repeat("☆", 5 - (int)$avaliacao['nota']);
                    ?>
                </div>

                <p>
                    <?= nl2br(escapar($avaliacao['comentario'])) ?>
                </p>

                <p class="data-avaliacao">
                    Avaliado em:
                    <?= date(
                        'd/m/Y H:i',
                        strtotime($avaliacao['data_avaliacao'])
                    ) ?>
                </p>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

    <p>
        <a href="index.php">← Voltar para a loja</a>
    </p>

</div>

</body>
</html>
