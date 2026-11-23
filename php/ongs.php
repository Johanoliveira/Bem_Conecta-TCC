<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

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
        COALESCE(SUM(DISTINCT c.meta), 0) AS metaTotal,
        COALESCE(SUM(d.valor), 0) AS totalArrecadado
    FROM ONGs o
    INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
    LEFT JOIN campanhas c ON c.idONG = o.idONG
    LEFT JOIN doacoes d ON d.idCampanha = c.idCampanha
    GROUP BY o.idONG, mu.nome, mu.email, mu.telefone, mu.fotoPerfil, o.descricao, o.Website, o.instagram, o.facebook
    ORDER BY mu.nome ASC
";

$resultado = $conexao->query($sqlOngs);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ONGs Parceiras - Bem Conecta</title>
    <link rel="stylesheet" href="../css/inicialPage.css">
    <link rel="stylesheet" href="../css/header.css">
    <style>
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
            gap: 12px;
        }
        .ong-topo img {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
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
    </style>
</head>
<body>
<?php require_once "header.php"; ?>

<main class="feed" style="max-width: 1100px; margin: 24px auto; padding: 0 16px;">
    <section class="welcome-card">
        <h2>ONGs Cadastradas e Metas de Arrecadação</h2>
        <p>Conheça as organizações parceiras, acompanhe o progresso das metas e contribua diretamente.</p>
    </section>

    <div class="ongs-grid">
        <?php if ($resultado && $resultado->num_rows > 0): ?>
            <?php while ($ong = $resultado->fetch_assoc()): ?>
                <?php
                    $foto = $ong["fotoPerfil"];
                    if (empty($foto) || in_array($foto, ["perfil.png", "img/perfil/perfil.png"], true)) {
                        $foto = "img/jpg/ftPerfil.jpg";
                    }
                    $meta = (float) $ong["metaTotal"];
                    $arrecadado = (float) $ong["totalArrecadado"];
                    $porcentagem = $meta > 0 ? min(100, round(($arrecadado / $meta) * 100)) : 0;
                ?>
                <article class="ong-box">
                    <div class="ong-topo">
                        <img src="../<?php echo htmlspecialchars($foto); ?>" alt="Logo ONG">
                        <div>
                            <h3>
                                <a href="perfilONG.php?idONG=<?php echo (int) $ong["idONG"]; ?>" style="color: inherit; text-decoration: none;">
                                    <?php echo htmlspecialchars($ong["nome"]); ?>
                                </a>
                            </h3>
                            <small><?php echo htmlspecialchars($ong["email"]); ?></small>
                        </div>
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
                        <a href="perfilONG.php?idONG=<?php echo (int) $ong["idONG"]; ?>" class="btn-ver-perfil-ong">
                            Ver Perfil
                        </a>
                        <a href="../html/pagamento.html" class="donate-btn" style="flex:1; text-align:center; text-decoration:none; padding:10px;">
                            Apoiar ONG
                        </a>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Nenhuma ONG cadastrada no momento.</p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>