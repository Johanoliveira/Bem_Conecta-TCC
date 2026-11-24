<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

$idONG = (int) ($_GET["idONG"] ?? 0);
if ($idONG <= 0) {
    header("Location: ongs.php");
    exit;
}

// Busca dados da ONG
$sqlOng = "
    SELECT
        o.idONG,
        o.descricao,
        o.CNPJ,
        o.Website,
        o.instagram,
        o.facebook,
        o.linkX,
        mu.nome,
        mu.email,
        mu.telefone,
        mu.fotoPerfil,
        mu.dataCriacao
    FROM ONGs o
    INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
    WHERE o.idONG = ?
    LIMIT 1
";

$stmt = $conexao->prepare($sqlOng);
$stmt->bind_param("i", $idONG);
$stmt->execute();
$ong = $stmt->get_result()->fetch_assoc();
$stmt->close();

$usuarioId = (int) $_SESSION["usuario_id"];
$podeFavoritar = false;
$ongFavoritada = false;

$stmtUc = $conexao->prepare("SELECT idUsuarioComum FROM UsuarioComum WHERE idMoldeUsuario = ? LIMIT 1");
if ($stmtUc) {
    $stmtUc->bind_param("i", $usuarioId);
    $stmtUc->execute();
    $ucRow = $stmtUc->get_result()->fetch_assoc();
    $stmtUc->close();

    if ($ucRow) {
        $podeFavoritar = true;
        $idUsuarioComum = (int) $ucRow["idUsuarioComum"];

        $stmtFav = $conexao->prepare("SELECT 1 FROM favoritos WHERE idUsuarioComum = ? AND idONG = ? LIMIT 1");
        if ($stmtFav) {
            $stmtFav->bind_param("ii", $idUsuarioComum, $idONG);
            $stmtFav->execute();
            $ongFavoritada = $stmtFav->get_result()->num_rows > 0;
            $stmtFav->close();
        }
    }
}

if (!$ong) {
    header("Location: ongs.php");
    exit;
}

$fotoOng = $ong["fotoPerfil"];
if (empty($fotoOng) || in_array($fotoOng, ["perfil.png", "img/perfil/perfil.png"], true)) {
    $fotoOng = "img/jpg/ftPerfil.jpg";
}

// Busca campanhas da ONG com total arrecadado
$campanhas = [];
$totalArrecadadoGeral = 0.0;

$sqlCamp = "
    SELECT
        c.idCampanha,
        c.titulo,
        c.descricao,
        c.meta,
        c.status,
        COALESCE(SUM(d.valor), 0) AS totalArrecadado
    FROM campanhas c
    LEFT JOIN doacoes d ON d.idCampanha = c.idCampanha
    WHERE c.idONG = ?
    GROUP BY c.idCampanha, c.titulo, c.descricao, c.meta, c.status
    ORDER BY c.dataInicio DESC
";
$stmtCamp = $conexao->prepare($sqlCamp);
$stmtCamp->bind_param("i", $idONG);
$stmtCamp->execute();
$resCamp = $stmtCamp->get_result();
while ($row = $resCamp->fetch_assoc()) {
    $campanhas[] = $row;
    $totalArrecadadoGeral += (float) $row["totalArrecadado"];
}
$stmtCamp->close();

// Busca publicações da ONG
$posts = [];
$sqlPosts = "
    SELECT
        p.idPost,
        p.titulo,
        p.conteudo,
        p.descricao,
        p.dataPublicacao,
        c.titulo AS tituloCampanha
    FROM posts p
    LEFT JOIN campanhas c ON p.idCampanha = c.idCampanha
    WHERE p.idONG = ?
    ORDER BY p.dataPublicacao DESC
";
$stmtPosts = $conexao->prepare($sqlPosts);
$stmtPosts->bind_param("i", $idONG);
$stmtPosts->execute();
$resPosts = $stmtPosts->get_result();
while ($row = $resPosts->fetch_assoc()) {
    $posts[] = $row;
}
$stmtPosts->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($ong["nome"]); ?> - Bem Conecta</title>
    <link rel="stylesheet" href="../css/perfil.css?v=6">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/sidebar.css">
</head>
<body>

<?php require_once "header.php"; ?>

<main class="pagina">
    <?php require_once "sidebar.php"; ?>

    <section class="conteudo-perfil">
        <div class="card-perfil">
            <div class="perfil-cabecalho">
                <div class="foto-perfil">
                    <img src="../<?php echo htmlspecialchars($fotoOng); ?>" alt="Foto da ONG">
                </div>

                <div class="informacoes-principais">
                    <h2><?php echo htmlspecialchars($ong["nome"]); ?></h2>
                    <p class="email">
                        <i class="fa-solid fa-envelope"></i>
                        <?php echo htmlspecialchars($ong["email"]); ?>
                    </p>
                    <p class="membro">
                        <i class="fa-solid fa-calendar"></i>
                        Na plataforma desde <?php echo date("d/m/Y", strtotime($ong["dataCriacao"])); ?>
                    </p>
                </div>

                <div class="acoes-perfil" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php if ($podeFavoritar): ?>
                        <button
                            type="button"
                            id="btnFavoritarPerfilOng"
                            data-id-ong="<?php echo (int) $idONG; ?>"
                            class="botao-editar"
                            style="background: <?php echo $ongFavoritada ? '#e63946' : '#ffffff'; ?>; color: <?php echo $ongFavoritada ? '#ffffff' : '#1b9e2a'; ?>; border: 2px solid <?php echo $ongFavoritada ? '#e63946' : '#1b9e2a'; ?>;"
                        >
                            <i class="fa-<?php echo $ongFavoritada ? 'solid' : 'regular'; ?> fa-heart"></i>
                            <span><?php echo $ongFavoritada ? 'ONG Favoritada' : 'Favoritar ONG'; ?></span>
                        </button>
                    <?php endif; ?>

                    <a href="../html/pagamento.html" class="botao-editar" style="text-decoration: none;">
                        <i class="fa-solid fa-hand-holding-heart"></i> Doar para esta ONG
                    </a>
                </div>
            </div>

            <hr>

            <div class="secao">
                <h2>Sobre a Organização</h2>
                <div class="grid-informacoes">
                    <div class="campo" style="grid-column: 1 / -1;">
                        <span>Descrição</span>
                        <p><?php echo htmlspecialchars($ong["descricao"]); ?></p>
                    </div>

                    <div class="campo">
                        <span>CNPJ</span>
                        <p><?php echo htmlspecialchars($ong["CNPJ"]); ?></p>
                    </div>

                    <div class="campo">
                        <span>Telefone</span>
                        <p><?php echo htmlspecialchars($ong["telefone"] ?: "-"); ?></p>
                    </div>

                    <?php if (!empty($ong["Website"]) || !empty($ong["instagram"]) || !empty($ong["facebook"])): ?>
                        <div class="campo" style="grid-column: 1 / -1;">
                            <span>Canais Oficiais</span>
                            <p style="display:flex; gap:16px; flex-wrap:wrap; margin-top:8px;">
                                <?php if (!empty($ong["Website"])): ?>
                                    <a href="<?php echo htmlspecialchars($ong["Website"]); ?>" target="_blank" style="color:#1b8a32;">🌐 Website</a>
                                <?php endif; ?>
                                <?php if (!empty($ong["instagram"])): ?>
                                    <a href="<?php echo htmlspecialchars($ong["instagram"]); ?>" target="_blank" style="color:#1b8a32;">📸 Instagram</a>
                                <?php endif; ?>
                                <?php if (!empty($ong["facebook"])): ?>
                                    <a href="<?php echo htmlspecialchars($ong["facebook"]); ?>" target="_blank" style="color:#1b8a32;">📘 Facebook</a>
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ESTATÍSTICAS DA ONG -->
        <div class="estatisticas">
            <div class="card-estatistica">
                <div class="icone verde"><i class="fa-solid fa-hand-holding-heart"></i></div>
                <div>
                    <span>Total Arrecadado</span>
                    <h2>R$ <?php echo number_format($totalArrecadadoGeral, 2, ",", "."); ?></h2>
                </div>
            </div>

            <div class="card-estatistica">
                <div class="icone"><i class="fa-solid fa-bullseye"></i></div>
                <div>
                    <span>Campanhas</span>
                    <h2><?php echo count($campanhas); ?></h2>
                </div>
            </div>

            <div class="card-estatistica">
                <div class="icone"><i class="fa-solid fa-newspaper"></i></div>
                <div>
                    <span>Publicações</span>
                    <h2><?php echo count($posts); ?></h2>
                </div>
            </div>
        </div>

        <!-- CAMPANHAS DA ONG -->
        <div class="atividade">
            <h2>Campanhas de <?php echo htmlspecialchars($ong["nome"]); ?></h2>

            <?php if (empty($campanhas)): ?>
                <div class="atividade-item">
                    <div class="atividade-icone">🎯</div>
                    <div>
                        <h3>Nenhuma campanha cadastrada</h3>
                        <p>Esta ONG ainda não abriu campanhas específicas.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="lista-campanhas-ong">
                    <?php foreach ($campanhas as $camp): ?>
                        <?php
                            $meta = (float) ($camp["meta"] ?? 0);
                            $arrecadado = (float) $camp["totalArrecadado"];
                            $porcentagem = $meta > 0 ? min(100, round(($arrecadado / $meta) * 100)) : 0;
                        ?>
                        <div class="card-campanha-perfil">
                            <div class="topo-campanha-perfil">
                                <h3><?php echo htmlspecialchars($camp["titulo"]); ?></h3>
                                <span class="status-campanha"><?php echo htmlspecialchars(ucfirst($camp["status"])); ?></span>
                            </div>

                            <p class="desc-campanha-perfil"><?php echo htmlspecialchars($camp["descricao"]); ?></p>

                            <div class="valores-campanha-perfil">
                                <span>
                                    Arrecadado: <strong>R$ <?php echo number_format($arrecadado, 2, ",", "."); ?></strong>
                                    <?php if ($meta > 0): ?>
                                        de <strong>R$ <?php echo number_format($meta, 2, ",", "."); ?></strong> (<?php echo $porcentagem; ?>%)
                                    <?php endif; ?>
                                </span>
                            </div>

                            <?php if ($meta > 0): ?>
                                <div class="barra-progresso-container">
                                    <div class="barra-progresso-fill" style="width: <?php echo $porcentagem; ?>%;"></div>
                                </div>
                            <?php endif; ?>

                            <a href="../html/pagamento.html?idCampanha=<?php echo (int) $camp["idCampanha"]; ?>"
                               class="botao-editar"
                               style="text-align:center; text-decoration:none; margin-top:6px;">
                                Doar para esta Campanha
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- PUBLICAÇÕES DA ONG -->
        <div class="atividade">
            <h2>Publicações de <?php echo htmlspecialchars($ong["nome"]); ?></h2>

            <?php if (empty($posts)): ?>
                <div class="atividade-item">
                    <div class="atividade-icone">📰</div>
                    <div>
                        <h3>Nenhuma publicação ainda</h3>
                        <p>Esta ONG ainda não fez postagens no feed.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="lista-posts-perfil">
                    <?php foreach ($posts as $post): ?>
                        <article class="item-post-perfil">
                            <img
                                src="<?php echo htmlspecialchars($post["conteudo"]); ?>"
                                alt="<?php echo htmlspecialchars($post["titulo"]); ?>"
                                class="thumb-post-perfil"
                            >
                            <div class="info-post-perfil">
                                <div class="meta-post-perfil">
                                    <span><?php echo date("d/m/Y", strtotime($post["dataPublicacao"])); ?></span>
                                    <?php if (!empty($post["tituloCampanha"])): ?>
                                        <span class="badge-campanha-post">🎯 <?php echo htmlspecialchars($post["tituloCampanha"]); ?></span>
                                    <?php endif; ?>
                                </div>
                                <h3><?php echo htmlspecialchars($post["titulo"]); ?></h3>
                                <p><?php echo htmlspecialchars($post["descricao"]); ?></p>
                            </div>
                            <a href="../html/pagamento.html?idPost=<?php echo (int) $post["idPost"]; ?>"
                               class="botao-editar"
                               style="text-decoration:none; flex-shrink:0;">
                                Doar Agora
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </section>
</main>

<script>
document.getElementById("btnFavoritarPerfilOng")?.addEventListener("click", async function () {
    const btn = this;
    if (btn.disabled) return;
    btn.disabled = true;

    try {
        const resp = await fetch("favoritarOng.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ idONG: Number(btn.dataset.idOng) })
        });
        const dados = await resp.json();

        if (!resp.ok || !dados.sucesso) {
            alert(dados.erro || "Não foi possível favoritar esta ONG.");
            return;
        }

        const fav = Boolean(dados.favoritado);
        btn.style.background = fav ? "#e63946" : "#ffffff";
        btn.style.color = fav ? "#ffffff" : "#1b9e2a";
        btn.style.borderColor = fav ? "#e63946" : "#1b9e2a";
        btn.querySelector("i").className = fav ? "fa-solid fa-heart" : "fa-regular fa-heart";
        btn.querySelector("span").textContent = fav ? "ONG Favoritada" : "Favoritar ONG";

        const contadorSidebar = document.getElementById("sbContadorFavoritas");
        if (contadorSidebar && typeof dados.totalFavoritos === "number") {
            contadorSidebar.textContent = String(dados.totalFavoritos);
        }
    } catch (err) {
        alert("Erro de conexão ao atualizar favoritos.");
    } finally {
        btn.disabled = false;
    }
});
</script>

</body>
</html>