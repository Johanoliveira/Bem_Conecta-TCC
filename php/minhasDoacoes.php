<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

$idMUsuario = (int) $_SESSION["usuario_id"];

$doacoes = [];
$totalDoado = 0.0;
$quantidadeDoacoes = 0;

$sql = "
    SELECT
        d.idDoacao,
        d.idCampanha,
        d.valor,
        d.dataDoacao,
        c.titulo AS nomeCampanha,
        o.idONG,
        mu.nome AS nomeONG,
        mu.fotoPerfil AS fotoONG
    FROM doacoes d
    INNER JOIN UsuarioComum uc ON d.idUsuarioComum = uc.idUsuarioComum
    INNER JOIN campanhas c ON d.idCampanha = c.idCampanha
    INNER JOIN ONGs o ON c.idONG = o.idONG
    INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
    WHERE uc.idMoldeUsuario = ?
    ORDER BY d.dataDoacao DESC, d.idDoacao DESC
";

$stmt = $conexao->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $idMUsuario);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $fotoOng = trim((string) ($row["fotoONG"] ?? ""));
        if ($fotoOng === "" || in_array($fotoOng, ["perfil.png", "img/perfil/perfil.png"], true)) {
            $fotoOng = "../img/jpg/ftPerfil.jpg";
        } elseif (!str_starts_with($fotoOng, "../") && !str_starts_with($fotoOng, "http://") && !str_starts_with($fotoOng, "https://")) {
            $fotoOng = "../" . ltrim($fotoOng, "/");
        }
        $row["fotoONG_url"] = $fotoOng;

        $doacoes[] = $row;
        $totalDoado += (float) $row["valor"];
        $quantidadeDoacoes++;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Doações - Bem Conecta</title>
    <link rel="stylesheet" href="../css/minhasDoacoes.css?v=4">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/sidebar.css">
</head>
<body>
<?php require_once "header.php"; ?>

<div class="layout-doacoes">
    <?php require_once "sidebar.php"; ?>

    <div class="layout-doacoes">
    <?php require_once "sidebar.php"; ?>

    <main class="conteudo-doacoes">
        <section class="banner-doacoes">
            <h1>💚 Minhas Doações</h1>
            <p>Acompanhe todas as contribuições que você já realizou pelo Bem Conecta. ✨</p>
        </section>

        <!-- CARDS DE RESUMO EM LIQUID GLASS -->
        <section class="resumo-grid">
            <div class="card-resumo glass-card">
                <span class="resumo-label">💰 Total Doado</span>
                <strong class="resumo-valor resumo-valor-destaque">R$ <?php echo number_format($totalDoado, 2, ',', '.'); ?></strong>
            </div>

            <div class="card-resumo glass-card">
                <span class="resumo-label">🎁 Quantidade de Doações</span>
                <strong class="resumo-valor"><?php echo (int) $quantidadeDoacoes; ?></strong>
            </div>
        </section>

        <!-- HISTÓRICO DE DOAÇÕES -->
        <section class="lista-doacoes">
            <?php if (empty($doacoes)): ?>
                <div class="card-doacao vazio">
                    <h3>🌱 Nenhuma doação realizada ainda</h3>
                    <p>Explore as campanhas e apoie causas sociais pelo Bem Conecta.</p>
                    <a href="campanhas.php" class="btn-doar-novamente">🚀 Ver Campanhas</a>
                </div>
            <?php else: ?>
                <?php foreach ($doacoes as $doacao): ?>
                    <article class="card-doacao">
                        <div class="doacao-esq">
                            <img
                                src="<?php echo htmlspecialchars($doacao["fotoONG_url"], ENT_QUOTES, 'UTF-8'); ?>"
                                alt="Foto da ONG <?php echo htmlspecialchars($doacao["nomeONG"], ENT_QUOTES, 'UTF-8'); ?>"
                                class="foto-ong-doacao"
                            >
                            <div class="doacao-info">
                                <h3>🎯 <?php echo htmlspecialchars($doacao["nomeCampanha"]); ?></h3>
                                <p class="nome-ong-direto">🏛️ <?php echo htmlspecialchars($doacao["nomeONG"]); ?></p>
                                <span class="data-doacao">
                                    🕒 Data: <?php echo date("d/m/Y H:i", strtotime($doacao["dataDoacao"])); ?>
                                </span>
                            </div>
                        </div>

                        <div class="doacao-dir">
                            <span class="badge-valor">
                                💵 R$ <?php echo number_format((float) $doacao["valor"], 2, ',', '.'); ?>
                            </span>
                            <a
                                href="../html/pagamento.html?idCampanha=<?php echo (int) $doacao["idCampanha"]; ?>"
                                class="btn-doar-novamente"
                            >
                                💚 Doar Novamente
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
</div>
</div>
</body>
</html>