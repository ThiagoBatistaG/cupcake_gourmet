
<?php

session_start();

require_once "config/conexao.php";

// Receber o termo digitado na busca
$busca = trim($_GET['busca'] ?? '');

// Verificar se existe uma pesquisa
if ($busca !== '') {

    $sql = "SELECT * FROM produtos
            WHERE ativo = TRUE
            AND (nome LIKE :busca OR descricao LIKE :busca)
            ORDER BY id DESC";

    $consulta = $conexao->prepare($sql);

    $consulta->execute([
        'busca' => '%' . $busca . '%'
    ]);

} else {

    // Mostrar todos os produtos quando não houver pesquisa
    $sql = "SELECT * FROM produtos
            WHERE ativo = TRUE
            ORDER BY id DESC";

    $consulta = $conexao->query($sql);
}

$produtos = $consulta->fetchAll(PDO::FETCH_ASSOC);

?>


<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cupcake Gourmet</title>

    <link rel="stylesheet" href="public/css/estilo.css">
</head>

<body>

<header>

    <div class="logo">Cupcake Gourmet</div>

    <nav>
    <a href="index.php">Início</a>
    <a href="#produtos">Produtos</a>
    <a href="carrinho.php">Meu carrinho</a>

    <?php if (isset($_SESSION['usuario_id'])): ?>

        <span>
            Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>
        </span>
        <a href="meus_pedidos.php">Meus pedidos</a>
        <a href="notificacoes.php">Notificações</a>
         <?php if ($_SESSION['usuario_tipo'] === 'admin'): ?>
        <a href="painel_admin.php">Painel administrativo</a>
    <?php endif; ?>
        <a href="sair.php">Sair</a>

    <?php else: ?>

        <a href="login.php">Entrar</a>
        <a href="cadastro.php">Cadastrar</a>

    <?php endif; ?>
</nav>

</header>

<section class="banner">

    <h1>Pequenos momentos, grandes sabores!</h1>

    <p>
        Descubra nossos cupcakes artesanais,
        preparados com carinho para você.
    </p>

</section>

<section class="catalogo" id="produtos">

    <h2>Nossos Cupcakes</h2>

    <form method="GET" action="index.php" class="busca-produtos">

        <input
            type="text"
            name="busca"
            placeholder="Digite o nome do cupcake..."
            value="<?= htmlspecialchars($busca) ?>"
        >

        <button type="submit" class="botao">
            🔍 Buscar
        </button>

        <?php if ($busca !== ''): ?>
            <a href="index.php" class="botao">
                Limpar busca
            </a>
        <?php endif; ?>

    </form>

    <?php if (count($produtos) === 0): ?>

        <p>Nenhum cupcake encontrado para essa pesquisa.</p>

    <?php endif; ?>

    <div class="produtos">

        <?php foreach ($produtos as $produto): ?>

            <div class="produto">

                <h3>
                    <?= htmlspecialchars($produto['nome']) ?>
                </h3>

                <p>
                    <?= htmlspecialchars($produto['descricao']) ?>
                </p>

                <div class="preco">
                    R$ <?= number_format(
                        $produto['preco'],
                        2,
                        ',',
                        '.'
                    ) ?>
                </div>
<p class="estoque">
    <?php if ($produto['estoque'] > 0): ?>

        <span style="color: green;">
            Disponível: <?= (int) $produto['estoque'] ?> unidades
        </span>

    <?php else: ?>

        <span style="color: red;">
            Produto esgotado
        </span>

    <?php endif; ?>
</p>
                <form method="POST" action="carrinho.php">

    <input type="hidden" name="acao" value="adicionar">

    <input
        type="hidden"
        name="produto_id"
        value="<?= $produto['id'] ?>"
    >

    <input type="hidden" name="quantidade" value="1">

   <?php if ($produto['estoque'] > 0): ?>

    <button class="botao" type="submit">
        Adicionar ao carrinho
    </button>

<?php else: ?>

    <button class="botao" type="button" disabled>
        Produto esgotado
    </button>

<?php endif; ?>


</form>

<a
    href="avaliar_produto.php?produto_id=<?= $produto['id'] ?>"
    class="botao"
    style="
        display: block;
        text-align: center;
        text-decoration: none;
        margin-top: 10px;
        box-sizing: border-box;
    "
>
    ⭐ Avaliar produto


<a
    href="ver_avaliacoes.php?produto_id=<?= $produto['id'] ?>"
    class="botao"
    style="
        display: block;
        text-align: center;
        text-decoration: none;
        margin-top: 10px;
        box-sizing: border-box;
    "
>
    ⭐ Ver avaliações
</a>

</a>

</div>

<?php endforeach; ?>


    </div>

</section>

<footer>

    <p>
        Cupcake Gourmet - Todos os direitos reservados.
    </p>

</footer>

</body>
</html>
