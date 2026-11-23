<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

$idUsuario = (int) $_SESSION["usuario_id"];
$nivel = 1;
$stmtNivel = $conexao->prepare("SELECT nivelDeSeguranca FROM MoldeUsuario WHERE idMUsuario = ?");
if ($stmtNivel) {
    $stmtNivel->bind_param("i", $idUsuario);
    $stmtNivel->execute();
    $resN = $stmtNivel->get_result()->fetch_assoc();
    $stmtNivel->close();
    if ($resN) $nivel = (int) $resN["nivelDeSeguranca"];
}

$conexao->query("
    UPDATE campanhas
    SET status = 'inativa'
    WHERE status = 'ativa'
      AND dataFim IS NOT NULL
      AND dataFim <= NOW()
");

$sqlCampanhas = "
    SELECT
        c.idCampanha,
        c.titulo,
        c.descricao,
        c.meta,
        c.dataInicio,
        c.dataFim,
        c.status,
        mu.nome AS nomeONG,
        COALESCE(SUM(d.valor), 0) AS totalArrecadado
    FROM campanhas c
    INNER JOIN ONGs o ON c.idONG = o.idONG
    INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
    LEFT JOIN doacoes d ON d.idCampanha = c.idCampanha
    GROUP BY c.idCampanha, c.titulo, c.descricao, c.meta, c.dataInicio, c.dataFim, c.status, mu.nome
    ORDER BY c.dataInicio DESC
";

$resultado = $conexao->query($sqlCampanhas);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campanhas - Bem Conecta</title>
    <link rel="stylesheet" href="../css/inicialPage.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <style>
        .campanhas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .campanha-card {
            background: #fff;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
        }
        .tag-ong {
            display: inline-block;
            background: #e8f8eb;
            color: #1b9e2a;
            font-weight: bold;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        .barra-meta {
            width: 100%;
            height: 10px;
            background: #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
            margin-top: 6px;
        }
        .barra-progresso {
            height: 100%;
            background: #1b9e2a;
        }
    </style>
</head>
<body>
<?php require_once "header.php"; ?>

<div class="layout-com-sidebar">
    <?php require_once "sidebar.php"; ?>

    <main class="feed">
        <section class="welcome-card">
            <h2>Campanhas de Arrecadação</h2>
            <p>Escolha uma campanha ativa e acompanhe quanto já foi arrecadado para cada causa.</p>
            <?php if ($nivel >= 2): ?>
                <a href="criarCampanha.php" class="donate-btn" style="display:inline-block; margin-top:12px; text-decoration:none;">
                    + Criar Nova Campanha
                </a>
            <?php endif; ?>
        </section>

        <div class="campanhas-grid">
            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while ($camp = $resultado->fetch_assoc()): ?>
                    <?php
                        $meta = (float) ($camp["meta"] ?? 0);
                        $arrecadado = (float) $camp["totalArrecadado"];
                        $porcentagem = $meta > 0 ? min(100, round(($arrecadado / $meta) * 100)) : 0;
                        $isInativa = ($camp["status"] === "inativa" || (!empty($camp["dataFim"]) && strtotime($camp["dataFim"]) <= time()));
                    ?>
                    <article class="campanha-card">
                        <div>
                            <span class="tag-ong"><?php echo htmlspecialchars($camp["nomeONG"]); ?></span>
                            <?php if ($isInativa): ?>
                                <span style="background:#fde2e1; color:#b71c1c; padding:4px 8px; border-radius:6px; font-size:0.8rem; font-weight:bold; margin-left:4px;">Inativa</span>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($camp["titulo"]); ?></h3>
                            <p style="margin-top:8px; color:#444;"><?php echo htmlspecialchars($camp["descricao"]); ?></p>
                        </div>

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

                        <div style="display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;">
                            <a href="campanha.php?idCampanha=<?php echo (int) $camp["idCampanha"]; ?>"
                               class="details-btn"
                               style="flex:1; text-align:center; text-decoration:none;">
                                Saiba mais
                            </a>

                            <?php if ($isInativa): ?>
                                <span style="flex:1; text-align:center; background:#ccc; color:#555; padding:12px; border-radius:8px; font-weight:bold;">
                                    Encerrada
                                </span>
                            <?php else: ?>
                                <a href="../html/pagamento.html?idCampanha=<?php echo (int) $camp["idCampanha"]; ?>"
                                   class="donate-btn"
                                   style="flex:1; text-align:center; text-decoration:none;">
                                    Doar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color:#fff;">Nenhuma campanha cadastrada no momento.</p>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>