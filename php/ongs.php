<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

$usuarioId = (int) $_SESSION["usuario_id"];
$podeFavoritar = false;
$favoritasIds = [];

// Verifica se é usuário comum (apenas usuários comuns podem favoritar ONGs)
$stmtUc = $conexao->prepare("SELECT idUsuarioComum FROM UsuarioComum WHERE idMoldeUsuario = ? LIMIT 1");
if ($stmtUc) {
    $stmtUc->bind_param("i", $usuarioId);
    $stmtUc->execute();
    $ucRow = $stmtUc->get_result()->fetch_assoc();
    $stmtUc->close();

    if ($ucRow) {
        $podeFavoritar = true;
        $idUsuarioComum = (int) $ucRow["idUsuarioComum"];

        $stmtFav = $conexao->prepare("SELECT idONG FROM favoritos WHERE idUsuarioComum = ?");
        if ($stmtFav) {
            $stmtFav->bind_param("i", $idUsuarioComum);
            $stmtFav->execute();
            $resFav = $stmtFav->get_result();
            while ($rowFav = $resFav->fetch_assoc()) {
                $favoritasIds[(int) $rowFav["idONG"]] = true;
            }
            $stmtFav->close();
        }
    }
}

$mostrarApenasFavoritas = $podeFavoritar && (($_GET["favoritas"] ?? "") === "1");

$sqlOngs = "
    SELECT
        o.idONG,
        mu.nome,
        mu.email,
        mu.telefone,
        mu.fotoPerfil,
        o.descricao,
        o.Website,
        o.instagram,
        o.facebook,
        COALESCE(m.metaTotal, 0) AS metaTotal,
        COALESCE(a.totalArrecadado, 0) AS totalArrecadado
    FROM ONGs o
    INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
    LEFT JOIN (
        SELECT idONG, SUM(COALESCE(meta, 0)) AS metaTotal
        FROM campanhas
        GROUP BY idONG
    ) m ON m.idONG = o.idONG
    LEFT JOIN (
        SELECT c.idONG, SUM(d.valor) AS totalArrecadado
        FROM doacoes d
        INNER JOIN campanhas c ON d.idCampanha = c.idCampanha
        GROUP BY c.idONG
    ) a ON a.idONG = o.idONG
    ORDER BY mu.nome ASC
";

$resultado = $conexao->query($sqlOngs);
$ongsLista = [];
if ($resultado) {
    while ($row = $resultado->fetch_assoc()) {
        $ongsLista[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ONGs Parceiras - Bem Conecta</title>
    <link rel="stylesheet" href="../css/inicialPage.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <style>
        .banner-ongs-topo {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
        }
        .btn-filtro-favoritas {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 12px;
            border: 2px solid #1b9e2a;
            background: #ffffff;
            color: #1b9e2a;
            font-weight: 700;
            font-size: 0.96rem;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .btn-filtro-favoritas:hover {
            background: #e8f8eb;
        }
        .btn-filtro-favoritas.ativo {
            background: #1b9e2a;
            color: #ffffff;
            box-shadow: 0 6px 16px rgba(27, 158, 42, 0.28);
        }
        .ongs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .ong-box {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .ong-topo {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .ong-topo-info {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1;
        }
        .ong-topo-info > div {
            min-width: 0;
        }
        .ong-topo-info small {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ong-topo img {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        .btn-favoritar-ong {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1.5px solid #d8e2dc;
            background: #f8faf9;
            color: #7c8a81;
            font-size: 1.15rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }
        .btn-favoritar-ong:hover {
            border-color: #e63946;
            color: #e63946;
            transform: scale(1.06);
        }
        .btn-favoritar-ong.favoritada {
            background: #ffe8eb;
            border-color: #e63946;
            color: #e63946;
        }
        .barra-meta {
            width: 100%;
            height: 10px;
            background: #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
        }
        .barra-progresso {
            height: 100%;
            background: #1b9e2a;
        }
        .redes-ong a {
            margin-right: 10px;
            color: #1b9e2a;
            font-weight: bold;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .acoes-ong-card {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        .btn-ver-perfil-ong {
            flex: 1;
            text-align: center;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            background: #e8f8eb;
            color: #1b9e2a;
            font-weight: bold;
        }
        .btn-ver-perfil-ong:hover {
            background: #d5f2db;
        }
        .aviso-sem-favoritas {
            grid-column: 1 / -1;
            background: #ececec;
            border-radius: 16px;
            padding: 36px 24px;
            text-align: center;
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .aviso-sem-favoritas h3 {
            color: #1b9e2a;
            font-size: 1.3rem;
        }
        .aviso-sem-favoritas p {
            color: #555;
        }
    </style>
</head>
<body>
<?php require_once "header.php"; ?>

<div class="layout-com-sidebar">
    <?php require_once "sidebar.php"; ?>

    <main class="feed">
        <section class="welcome-card">
            <div class="banner-ongs-topo">
                <div>
                    <h2>ONGs Cadastradas e Metas de Arrecadação</h2>
                    <p>Conheça as organizações parceiras, acompanhe o progresso das metas e contribua diretamente.</p>
                </div>

                <?php if ($podeFavoritar): ?>
                    <button
                        type="button"
                        id="btnFiltroFavoritas"
                        class="btn-filtro-favoritas <?php echo $mostrarApenasFavoritas ? 'ativo' : ''; ?>"
                        aria-pressed="<?php echo $mostrarApenasFavoritas ? 'true' : 'false'; ?>"
                    >
                        📌 ONGs Favoritas
                    </button>
                <?php endif; ?>
            </div>
        </section>

        <div class="ongs-grid" id="gridOngs">
            <?php if (!empty($ongsLista)): ?>
                <?php foreach ($ongsLista as $ong): ?>
                    <?php
                        $idOngAtual = (int) $ong["idONG"];
                        $isFavorita = isset($favoritasIds[$idOngAtual]);
                        $foto = $ong["fotoPerfil"];
                        if (empty($foto) || in_array($foto, ["perfil.png", "img/perfil/perfil.png"], true)) {
                            $foto = "img/jpg/ftPerfil.jpg";
                        }
                        $meta = (float) $ong["metaTotal"];
                        $arrecadado = (float) $ong["totalArrecadado"];
                        $porcentagem = $meta > 0 ? min(100, round(($arrecadado / $meta) * 100)) : 0;
                    ?>
                    <article
                        class="ong-box"
                        data-id-ong="<?php echo $idOngAtual; ?>"
                        data-favorita="<?php echo $isFavorita ? '1' : '0'; ?>"
                        style="<?php echo ($mostrarApenasFavoritas && !$isFavorita) ? 'display: none;' : ''; ?>"
                    >
                        <div class="ong-topo">
                            <div class="ong-topo-info">
                                <img src="../<?php echo htmlspecialchars($foto); ?>" alt="Logo ONG">
                                <div>
                                    <h3>
                                        <a href="perfilONG.php?idONG=<?php echo $idOngAtual; ?>" style="color: inherit; text-decoration: none;">
                                            <?php echo htmlspecialchars($ong["nome"]); ?>
                                        </a>
                                    </h3>
                                    <small><?php echo htmlspecialchars($ong["email"]); ?></small>
                                </div>
                            </div>

                            <?php if ($podeFavoritar): ?>
                                <button
                                    type="button"
                                    class="btn-favoritar-ong <?php echo $isFavorita ? 'favoritada' : ''; ?>"
                                    data-id-ong="<?php echo $idOngAtual; ?>"
                                    title="<?php echo $isFavorita ? 'Remover dos favoritos' : 'Favoritar ONG'; ?>"
                                >
                                    <?php echo $isFavorita ? '♥' : '♡'; ?>
                                </button>
                            <?php endif; ?>
                        </div>

                        <p><?php echo htmlspecialchars($ong["descricao"]); ?></p>

                        <div>
                            <small>
                                Arrecadado: <strong>R$ <?php echo number_format($arrecadado, 2, ',', '.'); ?></strong>
                                <?php if ($meta > 0): ?>
                                    de <strong>R$ <?php echo number_format($meta, 2, ',', '.'); ?></strong> (<?php echo $porcentagem; ?>%)
                                <?php endif; ?>
                            </small>
                            <div class="barra-meta">
                                <div class="barra-progresso" style="width: <?php echo $porcentagem; ?>%;"></div>
                            </div>
                        </div>

                        <div class="redes-ong">
                            <?php if (!empty($ong["Website"])): ?>
                                <a href="<?php echo htmlspecialchars($ong["Website"]); ?>" target="_blank">Site</a>
                            <?php endif; ?>
                            <?php if (!empty($ong["instagram"])): ?>
                                <a href="<?php echo htmlspecialchars($ong["instagram"]); ?>" target="_blank">Instagram</a>
                            <?php endif; ?>
                            <?php if (!empty($ong["facebook"])): ?>
                                <a href="<?php echo htmlspecialchars($ong["facebook"]); ?>" target="_blank">Facebook</a>
                            <?php endif; ?>
                        </div>

                        <div class="acoes-ong-card">
                            <a href="perfilONG.php?idONG=<?php echo $idOngAtual; ?>" class="btn-ver-perfil-ong">
                                Ver Perfil
                            </a>
                            <a href="../html/pagamento.html" class="donate-btn" style="flex:1; text-align:center; text-decoration:none; padding:10px;">
                                Apoiar ONG
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>

                <div class="aviso-sem-favoritas" id="avisoSemFavoritas">
                    <h3>Nenhuma ONG favoritada ainda</h3>
                    <p>Clique no coração (♡) nos cards das organizações para adicioná-las à sua lista de favoritas.</p>
                    <button type="button" id="btnMostrarTodasOngs" class="donate-btn">Ver todas as ONGs</button>
                </div>
            <?php else: ?>
                <p style="color:#fff;">Nenhuma ONG cadastrada no momento.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
(function () {
    const btnFiltro = document.getElementById("btnFiltroFavoritas");
    const btnMostrarTodas = document.getElementById("btnMostrarTodasOngs");
    const avisoSemFavoritas = document.getElementById("avisoSemFavoritas");
    const cardsOng = Array.from(document.querySelectorAll(".ong-box"));

    let apenasFavoritas = btnFiltro?.classList.contains("ativo") || false;

    function aplicarFiltroFavoritas() {
        let visiveis = 0;

        cardsOng.forEach(card => {
            const ehFavorita = card.dataset.favorita === "1";
            const mostrar = !apenasFavoritas || ehFavorita;
            card.style.display = mostrar ? "flex" : "none";
            if (mostrar) visiveis++;
        });

        if (avisoSemFavoritas) {
            avisoSemFavoritas.style.display = (apenasFavoritas && visiveis === 0) ? "flex" : "none";
        }

        if (btnFiltro) {
            btnFiltro.classList.toggle("ativo", apenasFavoritas);
            btnFiltro.setAttribute("aria-pressed", String(apenasFavoritas));
        }

        const novaUrl = apenasFavoritas ? "ongs.php?favoritas=1" : "ongs.php";
        window.history.replaceState(null, "", novaUrl);
    }

    btnFiltro?.addEventListener("click", () => {
        apenasFavoritas = !apenasFavoritas;
        aplicarFiltroFavoritas();
    });

    btnMostrarTodas?.addEventListener("click", () => {
        apenasFavoritas = false;
        aplicarFiltroFavoritas();
    });

    document.addEventListener("click", async (event) => {
        const btnFav = event.target.closest(".btn-favoritar-ong");
        if (!btnFav || btnFav.disabled) return;

        const idONG = Number(btnFav.dataset.idOng);
        btnFav.disabled = true;

        try {
            const resp = await fetch("favoritarOng.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ idONG })
            });

            const dados = await resp.json();

            if (!resp.ok || !dados.sucesso) {
                alert(dados.erro || "Não foi possível favoritar esta ONG.");
                return;
            }

            const card = btnFav.closest(".ong-box");
            const favoritada = Boolean(dados.favoritado);

            btnFav.classList.toggle("favoritada", favoritada);
            btnFav.textContent = favoritada ? "♥" : "♡";
            btnFav.title = favoritada ? "Remover dos favoritos" : "Favoritar ONG";

            if (card) {
                card.dataset.favorita = favoritada ? "1" : "0";
            }

            const contadorSidebar = document.getElementById("sbContadorFavoritas");
            if (contadorSidebar && typeof dados.totalFavoritos === "number") {
                contadorSidebar.textContent = String(dados.totalFavoritos);
            }

            aplicarFiltroFavoritas();
        } catch (err) {
            alert("Erro de conexão ao favoritar a ONG.");
        } finally {
            btnFav.disabled = false;
        }
    });

    aplicarFiltroFavoritas();
})();
</script>
</body>
</html>