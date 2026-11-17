
<?php

session_start();

require_once "config/conexao.php";

// Proteção: somente administradores podem acessar
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION['usuario_tipo'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

// Criar token de segurança
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensagemErro = "";

// Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {

        $mensagemErro = "Solicitação inválida. Atualize a página.";

    } else {

        $acao = $_POST['acao'] ?? '';

        try {

            // Cadastrar produto
            if ($acao === 'cadastrar') {

                $nome = trim($_POST['nome'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                $preco = str_replace(',', '.', $_POST['preco'] ?? '');
                $estoque = filter_var(
                    $_POST['estoque'] ?? null,
                    FILTER_VALIDATE_INT
                );
                $categoria = trim($_POST['categoria'] ?? '');
                $imagem = trim($_POST['imagem'] ?? '');

                if ($nome === '' || $preco === '' ||
                    !is_numeric($preco) || (float)$preco < 0 ||
                    $estoque === false || $estoque < 0) {

                    throw new Exception(
                        "Confira o nome, preço e estoque do produto."
                    );
                }

                if (strlen($nome) > 120 ||
                    strlen($categoria) > 80 ||
                    strlen($imagem) > 255) {

                    throw new Exception(
                        "Um dos campos ultrapassou o tamanho permitido."
                    );
                }

                $sql = "INSERT INTO produtos
                        (nome, descricao, preco, estoque, categoria, imagem, ativo)
                        VALUES (?, ?, ?, ?, ?, ?, 1)";

                $inserir = $conexao->prepare($sql);

                $inserir->execute([
                    $nome,
                    $descricao,
                    $preco,
                    $estoque,
                    $categoria !== '' ? $categoria : null,
                    $imagem !== '' ? $imagem : null
                ]);

                header("Location: gerenciar_produtos.php?sucesso=cadastrado");
                exit;
            }

            // Atualizar produto
            if ($acao === 'editar') {

                $id = filter_var(
                    $_POST['id'] ?? null,
                    FILTER_VALIDATE_INT
                );

                $nome = trim($_POST['nome'] ?? '');
                $descricao = trim($_POST['descricao'] ?? '');
                $preco = str_replace(',', '.', $_POST['preco'] ?? '');
                $estoque = filter_var(
                    $_POST['estoque'] ?? null,
                    FILTER_VALIDATE_INT
                );
                $categoria = trim($_POST['categoria'] ?? '');
                $imagem = trim($_POST['imagem'] ?? '');

                if (!$id || $nome === '' || $preco === '' ||
                    !is_numeric($preco) || (float)$preco < 0 ||
                    $estoque === false || $estoque < 0) {

                    throw new Exception(
                        "Confira os dados do produto."
                    );
                }

                if (strlen($nome) > 120 ||
                    strlen($categoria) > 80 ||
                    strlen($imagem) > 255) {

                    throw new Exception(
                        "Um dos campos ultrapassou o tamanho permitido."
                    );
                }

                $sql = "UPDATE produtos
                        SET nome = ?,
                            descricao = ?,
                            preco = ?,
                            estoque = ?,
                            categoria = ?,
                            imagem = ?
                        WHERE id = ?";

                $atualizar = $conexao->prepare($sql);

                $atualizar->execute([
                    $nome,
                    $descricao,
                    $preco,
                    $estoque,
                    $categoria !== '' ? $categoria : null,
                    $imagem !== '' ? $imagem : null,
                    $id
                ]);

                header("Location: gerenciar_produtos.php?sucesso=editado");
                exit;
            }

            // Desativar produto sem apagar seu histórico
            if ($acao === 'desativar') {

                $id = filter_var(
                    $_POST['id'] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (!$id) {
                    throw new Exception("Produto inválido.");
                }

                $sql = "UPDATE produtos SET ativo = 0 WHERE id = ?";

                $desativar = $conexao->prepare($sql);
                $desativar->execute([$id]);

                header("Location: gerenciar_produtos.php?sucesso=desativado");
                exit;
            }

            // Reativar produto
            if ($acao === 'ativar') {

                $id = filter_var(
                    $_POST['id'] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (!$id) {
                    throw new Exception("Produto inválido.");
                }

                $sql = "UPDATE produtos SET ativo = 1 WHERE id = ?";

                $ativar = $conexao->prepare($sql);
                $ativar->execute([$id]);

                header("Location: gerenciar_produtos.php?sucesso=ativado");
                exit;
            }

        } catch (Throwable $erro) {

            error_log($erro->getMessage());
            $mensagemErro = $erro->getMessage();
        }
    }
}

// Buscar todos os produtos
$consulta = $conexao->query(
    "SELECT * FROM produtos ORDER BY id DESC"
);

$produtos = $consulta->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gerenciar Produtos - Cupcake Gourmet</title>

    <link rel="stylesheet" href="public/css/estilo.css">

    <style>

        .gerenciamento {
            max-width: 1100px;
            margin: 35px auto;
            padding: 20px;
        }

        .bloco-admin {
            background: white;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.07);
        }

        .bloco-admin label {
            display: block;
            margin-top: 12px;
            font-weight: bold;
        }

        .bloco-admin input,
        .bloco-admin textarea {
            width: 100%;
            padding: 11px;
            margin-top: 6px;
            border: 1px solid #ddd;
            border-radius: 7px;
        }

        .bloco-admin textarea {
            min-height: 75px;
        }

        .produto-admin {
            border: 1px solid #eee;
            padding: 20px;
            border-radius: 10px;
            margin: 18px 0;
        }

        .produto-admin h3 {
            color: #a34f61;
        }

        .produto-inativo {
            opacity: 0.65;
            background: #f5f5f5;
        }

        .botao-secundario {
            background: #777;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 7px;
            cursor: pointer;
        }

        .botao-ativar {
            background: #397b50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 7px;
            cursor: pointer;
        }

        .mensagem-sucesso {
            background: #e7f7ec;
            color: #246b3b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .mensagem-erro {
            background: #fcebea;
            color: #a12d2d;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .acoes-produto {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

    </style>

</head>

<body>

<header>

    <div class="logo">Cupcake Gourmet</div>

    <nav>
        <a href="painel_admin.php">Painel</a>
        <a href="index.php">Ver loja</a>
        <a href="sair.php">Sair</a>
    </nav>

</header>

<main class="gerenciamento">

    <h1>Gerenciamento de produtos</h1>

    <p>
        Cadastre, edite, atualize o estoque e controle os produtos da loja.
    </p>

    <?php if (isset($_GET['sucesso'])): ?>

        <div class="mensagem-sucesso">

            <?php

            $mensagens = [
                'cadastrado' => 'Produto cadastrado com sucesso!',
                'editado' => 'Produto atualizado com sucesso!',
                'desativado' => 'Produto desativado com sucesso!',
                'ativado' => 'Produto reativado com sucesso!'
            ];

            echo htmlspecialchars(
                $mensagens[$_GET['sucesso']] ?? 'Operação realizada.'
            );

            ?>

        </div>

    <?php endif; ?>

    <?php if ($mensagemErro !== ''): ?>

        <div class="mensagem-erro">
            <?= htmlspecialchars($mensagemErro) ?>
        </div>

    <?php endif; ?>

    <section class="bloco-admin">

        <h2>Cadastrar novo cupcake</h2>

        <form method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <input type="hidden" name="acao" value="cadastrar">

            <label>Nome do produto *</label>
            <input type="text" name="nome" maxlength="120" required>

            <label>Descrição</label>
            <textarea name="descricao"></textarea>

            <label>Preço (R$) *</label>
            <input type="number" name="preco"
                   min="0" step="0.01" required>

            <label>Quantidade em estoque *</label>
            <input type="number" name="estoque"
                   min="0" step="1" required>

            <label>Categoria</label>
            <input type="text" name="categoria" maxlength="80">

            <label>Imagem (caminho ou endereço da imagem)</label>
            <input type="text" name="imagem" maxlength="255"
                   placeholder="Ex.: public/imagens/cupcake.jpg">

            <button type="submit" class="botao">
                Cadastrar produto
            </button>

        </form>

    </section>

    <section class="bloco-admin">

        <h2>Produtos cadastrados</h2>

        <?php if (empty($produtos)): ?>

            <p>Nenhum produto cadastrado.</p>

        <?php else: ?>

            <?php foreach ($produtos as $produto): ?>

                <article class="produto-admin
                    <?= $produto['ativo'] ? '' : 'produto-inativo' ?>">

                    <h3>
                        <?= htmlspecialchars($produto['nome']) ?>
                    </h3>

                    <p>
                        <strong>Código:</strong>
                        <?= (int) $produto['id'] ?>
                    </p>

                    <p>
                        <strong>Status:</strong>
                        <?= $produto['ativo'] ? 'Ativo' : 'Inativo' ?>
                    </p>

                    <form method="POST">

                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <input type="hidden" name="acao" value="editar">

                        <input type="hidden" name="id"
                               value="<?= (int) $produto['id'] ?>">

                        <label>Nome</label>
                        <input type="text" name="nome" maxlength="120"
                               value="<?= htmlspecialchars($produto['nome']) ?>"
                               required>

                        <label>Descrição</label>
                        <textarea name="descricao"><?= htmlspecialchars(
                            $produto['descricao'] ?? ''
                        ) ?></textarea>

                        <label>Preço (R$)</label>
                        <input type="number" name="preco"
                               min="0" step="0.01"
                               value="<?= htmlspecialchars($produto['preco']) ?>"
                               required>

                        <label>Estoque</label>
                        <input type="number" name="estoque"
                               min="0" step="1"
                               value="<?= (int) $produto['estoque'] ?>"
                               required>

                        <label>Categoria</label>
                        <input type="text" name="categoria" maxlength="80"
                               value="<?= htmlspecialchars(
                                   $produto['categoria'] ?? ''
                               ) ?>">

                        <label>Imagem</label>
                        <input type="text" name="imagem" maxlength="255"
                               value="<?= htmlspecialchars(
                                   $produto['imagem'] ?? ''
                               ) ?>">

                        <div class="acoes-produto">

                            <button type="submit" class="botao">
                                Salvar alterações
                            </button>

                        </div>

                    </form>

                    <div class="acoes-produto">

                        <?php if ($produto['ativo']): ?>

                            <form method="POST"
                                  onsubmit="return confirm('Deseja realmente desativar este produto?');">

                                <input type="hidden" name="csrf_token"
                                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                <input type="hidden" name="acao" value="desativar">

                                <input type="hidden" name="id"
                                       value="<?= (int) $produto['id'] ?>">

                                <button type="submit" class="botao-secundario">
                                    Desativar produto
                                </button>

                            </form>

                        <?php else: ?>

                            <form method="POST">

                                <input type="hidden" name="csrf_token"
                                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                <input type="hidden" name="acao" value="ativar">

                                <input type="hidden" name="id"
                                       value="<?= (int) $produto['id'] ?>">

                                <button type="submit" class="botao-ativar">
                                    Reativar produto
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

<footer>

    <p>Cupcake Gourmet - Área administrativa.</p>

</footer>

</body>
</html>
