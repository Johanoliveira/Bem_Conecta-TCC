<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

$idCampanha = (int) ($_GET["idCampanha"] ?? 0);
if ($idCampanha <= 0) {
    header("Location: campanhas.php");
    exit;
}

$conexao->query("
    UPDATE campanhas
    SET status = 'inativa'
    WHERE status = 'ativa'
      AND dataFim IS NOT NULL
      AND dataFim <= NOW()
");

// Busca os dados da campanha e da ONG responsável
$sqlCampanha = "
    SELECT
        c.idCampanha,
        c.idONG,
        c.titulo,
        c.descricao,
        c.meta,
        c.dataInicio,
        c.dataFim,
        c.status,
        mu.nome AS nomeONG,
        mu.fotoPerfil AS fotoONG,
        COALESCE(SUM(d.valor), 0) AS totalArrecadado,
        COUNT(d.idDoacao) AS totalDoacoes
    FROM campanhas c
    INNER JOIN ONGs o ON c.idONG = o.idONG
    INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
    LEFT JOIN doacoes d ON d.idCampanha = c.idCampanha
    WHERE c.idCampanha = ?
    GROUP BY
        c.idCampanha, c.idONG, c.titulo, c.descricao,
        c.meta, c.dataInicio, c.dataFim, c.status,
        mu.nome, mu.fotoPerfil
    LIMIT 1
";

$stmt = $conexao->prepare($sqlCampanha);
$stmt->bind_param("i", $idCampanha);
$stmt->execute();
$campanha = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$campanha) {
    header("Location: campanhas.php");
    exit;
}

$fotoOng = $campanha["fotoONG"];
if (empty($fotoOng) || in_array($fotoOng, ["perfil.png", "img/perfil/perfil.png"], true)) {
    $fotoOng = "img/jpg/ftPerfil.jpg";
}

$meta = (float) ($campanha["meta"] ?? 0);
$arrecadado = (float) $campanha["totalArrecadado"];
$porcentagem = $meta > 0 ? min(100, round(($arrecadado / $meta) * 100)) : 0;
$isInativa = ($campanha["status"] === "inativa" || (!empty($campanha["dataFim"]) && strtotime($campanha["dataFim"]) <= time()));

// Busca todos os posts vinculados a esta campanha
$postsCampanha = [];
$sqlPosts = "
    SELECT
        p.idPost,
        p.titulo,
        p.conteudo,
        p.descricao,
        p.palavrasChave,
        p.dataPublicacao,
        (SELECT COUNT(*) FROM curtidas cu WHERE cu.idPost = p.idPost) AS totalCurtidas,
        (SELECT COUNT(*) FROM comentarios co WHERE co.idPost = p.idPost) AS totalComentarios
    FROM posts p
    WHERE p.idCampanha = ?
    ORDER BY p.dataPublicacao DESC
";

$stmtPosts = $conexao->prepare($sqlPosts);
if ($stmtPosts) {
    $stmtPosts->bind_param("i", $idCampanha);
    $stmtPosts->execute();
    $resPosts = $stmtPosts->get_result();
    while ($row = $resPosts->fetch_assoc()) {
        $postsCampanha[] = $row;
    }
    $stmtPosts->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($campanha["titulo"]); ?> - Bem Conecta</title>
    <link rel="stylesheet" href="../css/inicialPage.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <style>
        .campanha-detalhe-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .campanha-detalhe-topo {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .ong-link-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            background: #e8f8eb;
            color: #1b9e2a;
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.92rem;
        }

        .ong-link-badge img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .status-badge.ativa {
            background: #dff4e2;
            color: #1b9e2a;
        }

        .status-badge.inativa {
            background: #fde2e1;
            color: #b71c1c;
        }

        .campanha-detalhe-card h1 {
            color: #22a848;
            font-size: 1.9rem;
        }

        .campanha-detalhe-descricao {
            color: #3d3d3d;
            line-height: 1.65;
            font-size: 1rem;
            white-space: pre-line;
        }

        .progresso-box {
            background: #f5f9f6;
            border: 1px solid #d9ecd9;
            border-radius: 14px;
            padding: 16px 20px;
        }

        .barra-meta {
            width: 100%;
            height: 12px;
            background: #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            margin: 10px 0 8px;
        }

        .barra-progresso {
            height: 100%;
            background: #1b9e2a;
        }

        .acoes-detalhe-campanha {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .secao-posts-campanha {
            margin-top: 8px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .secao-posts-campanha > h2 {
            color: #ffffff;
            font-size: 1.4rem;
        }

        .post-campanha-card {
            background: #ececec;
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
        }

        .post-campanha-card h3 {
            color: #22a848;
            font-size: 1.25rem;
            margin-bottom: 6px;
        }

        .post-campanha-data {
            display: block;
            color: #666;
            font-size: 0.88rem;
            margin-bottom: 14px;
        }

        .post-campanha-card img {
            width: 100%;
            max-height: 460px;
            object-fit: cover;
            border-radius: 14px;
            margin-bottom: 14px;
        }

        .post-campanha-card p {
            color: #2d2d2d;
            line-height: 1.6;
            white-space: pre-line;
            margin-bottom: 14px;
        }

        .post-campanha-rodape {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 12px;
            border-top: 1px solid #d4d4d4;
            color: #555;
            font-size: 0.9rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
<?php require_once "header.php"; ?>

<div class="layout-com-sidebar">
    <?php require_once "sidebar.php"; ?>

    <main class="feed" style="max-width: 980px;">
        <article class="campanha-detalhe-card">
            <div class="campanha-detalhe-topo">
                <a href="perfilONG.php?idONG=<?php echo (int) $campanha["idONG"]; ?>" class="ong-link-badge">
                    <img src="../<?php echo htmlspecialchars($fotoOng); ?>" alt="Logo ONG">
                    <span><?php echo htmlspecialchars($campanha["nomeONG"]); ?></span>
                </a>

                <span class="status-badge <?php echo $isInativa ? 'inativa' : 'ativa'; ?>">
                    <?php echo $isInativa ? "Campanha Encerrada" : "Campanha Ativa"; ?>
                </span>
            </div>

            <h1><?php echo htmlspecialchars($campanha["titulo"]); ?></h1>

            <p class="campanha-detalhe-descricao"><?php echo htmlspecialchars($campanha["descricao"]); ?></p>

            <div class="progresso-box">
                <div>
                    Arrecadado: <strong>R$ <?php echo number_format($arrecadado, 2, ',', '.'); ?></strong>
                    <?php if ($meta > 0): ?>
                        de <strong>R$ <?php echo number_format($meta, 2, ',', '.'); ?></strong> (<?php echo $porcentagem; ?>%)
                    <?php endif; ?>
                </div>

                <?php if ($meta > 0): ?>
                    <div class="barra-meta">
                        <div class="barra-progresso" style="width: <?php echo $porcentagem; ?>%;"></div>
                    </div>
                <?php endif; ?>

                <small style="color:#666;">
                    <?php echo (int) $campanha["totalDoacoes"]; ?> doação(ões) recebida(s)
                    <?php if (!empty($campanha["dataFim"])): ?>
                        · Prazo limite: <?php echo date("d/m/Y", strtotime($campanha["dataFim"])); ?>
                    <?php endif; ?>
                </small>
            </div>

            <div class="acoes-detalhe-campanha">
                <a href="campanhas.php" class="details-btn" style="text-decoration:none;">
                    ← Voltar para Campanhas
                </a>

                <?php if (!$isInativa): ?>
                    <a href="../html/pagamento.html?idCampanha=<?php echo (int) $campanha["idCampanha"]; ?>"
                       class="donate-btn"
                       style="text-decoration:none;">
                        Doar para esta Campanha
                    </a>
                <?php endif; ?>
            </div>
        </article>

        <section class="secao-posts-campanha">
            <h2>Publicações desta Campanha (<?php echo count($postsCampanha); ?>)</h2>

            <?php if (empty($postsCampanha)): ?>
                <div class="post-campanha-card">
                    <h3>Nenhuma publicação vinculada ainda</h3>
                    <p style="margin-bottom:0;">A ONG ainda não publicou atualizações específicas para esta campanha.</p>
                </div>
            <?php else: ?>
                <?php foreach ($postsCampanha as $post): ?>
                    <article class="post-campanha-card">
                        <h3><?php echo htmlspecialchars($post["titulo"]); ?></h3>
                        <span class="post-campanha-data">
                            Publicado em <?php echo date("d/m/Y \à\s H:i", strtotime($post["dataPublicacao"])); ?>
                        </span>

                        <?php if (!empty($post["conteudo"])): ?>
                            <img src="<?php echo htmlspecialchars($post["conteudo"]); ?>" alt="<?php echo htmlspecialchars($post["titulo"]); ?>">
                        <?php endif; ?>

                        <p><?php echo htmlspecialchars($post["descricao"]); ?></p>

                        <div class="post-campanha-rodape">
                            <div>
                                ❤️ <?php echo (int) $post["totalCurtidas"]; ?> curtida(s) ·
                                💬 <?php echo (int) $post["totalComentarios"]; ?> comentário(s)
                            </div>

                            <?php if (!empty($post["palavrasChave"])): ?>
                                <span>🏷️ <?php echo htmlspecialchars($post["palavrasChave"]); ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>