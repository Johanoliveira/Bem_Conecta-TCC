<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

$idUsuario = (int) $_SESSION["usuario_id"];
$nome = $_SESSION["usuario_nome"] ?? "Usuário";
$foto = $_SESSION["usuario_foto"] ?? "img/jpg/ftPerfil.jpg";

$sqlDoacoes = "
    SELECT
        d.idDoacao,
        d.valor,
        d.dataDoacao,
        c.idCampanha,
        c.titulo AS tituloCampanha,
        muOng.nome AS nomeONG
    FROM doacoes d
    INNER JOIN UsuarioComum uc ON d.idUsuarioComum = uc.idUsuarioComum
    INNER JOIN campanhas c ON d.idCampanha = c.idCampanha
    INNER JOIN ONGs o ON c.idONG = o.idONG
    INNER JOIN MoldeUsuario muOng ON o.idMoldeUsuario = muOng.idMUsuario
    WHERE uc.idMoldeUsuario = ?
    ORDER BY d.dataDoacao DESC
";

$stmt = $conexao->prepare($sqlDoacoes);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$resultado = $stmt->get_result();

$listaDoacoes = [];
$totalDoado = 0.0;

while ($row = $resultado->fetch_assoc()) {
    $listaDoacoes[] = $row;
    $totalDoado += (float) $row["valor"];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Doações - Bem Conecta</title>
    <link rel="stylesheet" href="../css/inicialPage.css">
    <style>
        .resumo-doacoes {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        .card-resumo {
            flex: 1;
            min-width: 200px;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        .card-resumo span {
            color: #666;
            font-size: 0.9rem;
        }
        .card-resumo h3 {
            color: #1b9e2a;
            font-size: 1.6rem;
            margin-top: 6px;
        }
        .lista-doacoes {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .item-doacao {
            background: #fff;
            padding: 18px 22px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .valor-badge {
            background: #e8f8eb;
            color: #1b9e2a;
            font-weight: bold;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 1.05rem;
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="logos">
        <div class="logo-foto"><img src="../img/logo/logo.webp" alt=""></div>
        <div class="logo-escrita"><img src="../img/logo/escrita.png" alt=""></div>
    </div>
    <nav class="menu">
        <a href="inicialPage.php">Início</a>
        <a href="campanhas.php">Campanhas</a>
        <a href="ongs.php">ONGs</a>
        <a href="minhasDoacoes.php">Doações</a>
    </nav>
    <div class="top-actions">
        <button class="profile-btn" onclick="window.location.href='perfil.php'">
            <img src="../php/<?php echo htmlspecialchars($foto); ?>" alt="Foto">
            Meu Perfil
        </button>
    </div>
</header>

<main class="feed" style="max-width: 960px; margin: 24px auto; padding: 0 16px;">
    <section class="welcome-card">
        <h2>Minhas Doações</h2>
        <p>Acompanhe todas as contribuições que você já realizou pelo Bem Conecta.</p>
    </section>

    <div class="resumo-doacoes">
        <div class="card-resumo">
            <span>Total Doado</span>
            <h3>R$ <?php echo number_format($totalDoado, 2, ',', '.'); ?></h3>
        </div>
        <div class="card-resumo">
            <span>Quantidade de Doações</span>
            <h3><?php echo count($listaDoacoes); ?></h3>
        </div>
    </div>

    <div class="lista-doacoes">
        <?php if (empty($listaDoacoes)): ?>
            <div class="item-doacao">
                <div>
                    <h3>Você ainda não realizou nenhuma doação</h3>
                    <p>Explore as campanhas ativas e apoie uma causa social.</p>
                </div>
                <a href="campanhas.php" class="donate-btn" style="text-decoration:none;">Ver Campanhas</a>
            </div>
        <?php else: ?>
            <?php foreach ($listaDoacoes as $doacao): ?>
                <div class="item-doacao">
                    <div>
                        <h3><?php echo htmlspecialchars($doacao["tituloCampanha"]); ?></h3>
                        <p>ONG: <strong><?php echo htmlspecialchars($doacao["nomeONG"]); ?></strong></p>
                        <small>Data: <?php echo date("d/m/Y H:i", strtotime($doacao["dataDoacao"])); ?></small>
                    </div>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span class="valor-badge">
                            R$ <?php echo number_format((float) $doacao["valor"], 2, ',', '.'); ?>
                        </span>
                        <a href="../html/pagamento.html?idCampanha=<?php echo (int) $doacao["idCampanha"]; ?>"
                           class="donate-btn"
                           style="text-decoration:none;">
                            Doar Novamente
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>
</body>
</html>