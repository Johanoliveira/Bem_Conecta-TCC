<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.html");
    exit;
}

require_once __DIR__ . "/../php/conexao.php";

$nome = $_SESSION["usuario_nome"];
$foto = $_SESSION["usuario_foto"];

$nivelDeSeguranca = 1;
$idOngLogada = 0;

$sqlNivel = "
    SELECT
        mu.nivelDeSeguranca,
        o.idONG
    FROM MoldeUsuario mu
    LEFT JOIN ONGs o
        ON o.idMoldeUsuario = mu.idMUsuario
    WHERE mu.idMUsuario = ?
    LIMIT 1
";

$stmtNivel = $conexao->prepare($sqlNivel);

if ($stmtNivel) {
    $usuarioId = (int) $_SESSION["usuario_id"];

    $stmtNivel->bind_param("i", $usuarioId);
    $stmtNivel->execute();

    $resultadoNivel = $stmtNivel->get_result();
    $usuario = $resultadoNivel->fetch_assoc();

    if ($usuario) {
        $nivelDeSeguranca = (int) $usuario["nivelDeSeguranca"];
        $idOngLogada = $usuario["idONG"] !== null ? (int) $usuario["idONG"] : 0;
    }

    $stmtNivel->close();
}

// Busca os 5 posts com mais curtidas para o painel "Campanhas em Destaque"
$postsDestaque = [];
$sqlDestaques = "
    SELECT
        p.idPost,
        p.idONG,
        p.idCampanha,
        p.titulo,
        p.conteudo,
        mu.nome AS nomeONG,
        (SELECT COUNT(*) FROM curtidas cu WHERE cu.idPost = p.idPost) AS totalCurtidas
    FROM posts p
    INNER JOIN ONGs o ON p.idONG = o.idONG
    INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
    ORDER BY totalCurtidas DESC, p.dataPublicacao DESC
    LIMIT 5
";

$resDestaques = $conexao->query($sqlDestaques);
if ($resDestaques) {
    while ($rowDest = $resDestaques->fetch_assoc()) {
        $postsDestaque[] = $rowDest;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem Conecta - Início</title>
    <link rel="stylesheet" href="../css/inicialPage.css?v=8">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/sidebar.css">
</head>

<body
    data-usuario-nome="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>"
    data-nivel-seguranca="<?php echo (int) $nivelDeSeguranca; ?>"
    data-id-ong="<?php echo (int) $idOngLogada; ?>">

<!-- TOPO -->
<?php require_once "header.php"; ?>

<!-- CONTEÚDO -->
<div class="layout">

    <!-- SIDEBAR ESQUERDA -->
    <?php require_once "sidebar.php"; ?>

    <!-- FEED CENTRAL -->
    <main class="feed">
        <section class="welcome-card">
            <h2>Bem-vindo de volta, <?php echo htmlspecialchars($nome); ?>!</h2>

            <p>
                Veja as campanhas mais recentes e continue apoiando
                projetos sociais que transformam vidas.
            </p>

            <?php if ($nivelDeSeguranca !== 1): ?>
                <a href="criarPost.php" class="create-post-btn">
                    + Criar post
                </a>
            <?php endif; ?>
        </section>

        <section class="posts" aria-label="Publicações">
            <div id="posts-container"></div>
        </section>
    </main>

    <!-- LATERAL DIREITA (TOP 5 MAIS CURTIDOS) -->
    <aside class="right-panel" id="rightPanelDestaques">
        <div class="sidebar-card">
            <div class="right-panel-topo">
                <button
                    type="button"
                    class="btn-minimizar-sidebar"
                    id="btnMinimizarRightPanel"
                    title="Minimizar destaques"
                >▶</button>
                <h3>Campanhas em Destaque</h3>
            </div>

            <?php if (empty($postsDestaque)): ?>
                <p style="color: rgba(255,255,255,0.82); font-size: 0.9rem;">
                    Nenhuma publicação em destaque no momento.
                </p>
            <?php else: ?>
                <?php foreach ($postsDestaque as $dest): ?>
                    <?php
                        $imgDest = !empty($dest["conteudo"]) ? $dest["conteudo"] : "../img/jpg/holder.jpg";
                        $qtdCurtidas = (int) $dest["totalCurtidas"];
                        $linkDest = !empty($dest["idCampanha"])
                            ? "campanha.php?idCampanha=" . (int) $dest["idCampanha"]
                            : "#post-" . (int) $dest["idPost"];
                    ?>
                    <a
                        href="<?php echo htmlspecialchars($linkDest, ENT_QUOTES, 'UTF-8'); ?>"
                        class="campaign"
                        title="<?php echo htmlspecialchars($dest["titulo"] . " (" . $qtdCurtidas . " curtida(s))", ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <img
                            src="<?php echo htmlspecialchars($imgDest, ENT_QUOTES, 'UTF-8'); ?>"
                            alt="<?php echo htmlspecialchars($dest["titulo"], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                        <div class="campaign-info">
                            <strong><?php echo htmlspecialchars($dest["titulo"]); ?></strong>
                            <span><?php echo htmlspecialchars($dest["nomeONG"]); ?></span>
                            <p>❤️ <?php echo $qtdCurtidas; ?> <?php echo $qtdCurtidas === 1 ? "curtida" : "curtidas"; ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

</div>
<script src="../js/inicialPage.js?v=8"></script>
</body>
</html>