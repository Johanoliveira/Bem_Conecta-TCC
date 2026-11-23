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


// Indicadores padrão
$totalDoado = 0;
$campanhasApoiadas = 0;
$ongsFavoritas = 0;

// Busca os indicadores do usuário conectado
$sql = "
    SELECT
        COALESCE((
            SELECT SUM(d.valor)
            FROM doacoes d
            INNER JOIN UsuarioComum uc
                ON uc.idUsuarioComum = d.idUsuarioComum
            WHERE uc.idMoldeUsuario = ?
        ), 0) AS totalDoado,

        (
            SELECT COUNT(DISTINCT d.idCampanha)
            FROM doacoes d
            INNER JOIN UsuarioComum uc
                ON uc.idUsuarioComum = d.idUsuarioComum
            WHERE uc.idMoldeUsuario = ?
        ) AS campanhasApoiadas,

        (
            SELECT COUNT(*)
            FROM favoritos f
            INNER JOIN UsuarioComum uc
                ON uc.idUsuarioComum = f.idUsuarioComum
            WHERE uc.idMoldeUsuario = ?
        ) AS ongsFavoritas
";

$stmt = $conexao->prepare($sql);

if ($stmt) {
    $usuarioId = (int) $_SESSION["usuario_id"];

    $stmt->bind_param(
        "iii",
        $usuarioId,
        $usuarioId,
        $usuarioId
    );

    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
        $dados = $resultado->fetch_assoc();

        if ($dados) {
            $totalDoado = (float) $dados["totalDoado"];
            $campanhasApoiadas = (int) $dados["campanhasApoiadas"];
            $ongsFavoritas = (int) $dados["ongsFavoritas"];
        }
    } else {
        error_log("Erro ao consultar indicadores: " . $stmt->error);
    }

    $stmt->close();

    
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem Conecta - Início</title>
    <link rel="stylesheet" href="../css/inicialPage.css">
    <link rel="stylesheet" href="../css/header.css">
</head>

<body
    data-usuario-nome="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>"
    data-nivel-seguranca="<?php echo (int) $nivelDeSeguranca; ?>"
    data-id-ong="<?php echo (int) $idOngLogada; ?>">

<!-- TOPO -->
<?php require_once "header.php"; ?>

<!-- CONTEÚDO -->
<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
    <div class="sidebar-card">
        <h3>Menu</h3>

        <a href="inicialPage.php">🏠 Página Inicial</a>
        <a href="minhasDoacoes.php">❤️ Minhas Doações</a>
        <a href="ongs.php">📌 ONGs Favoritas</a>
        <a href="campanhas.php">📄 Campanhas</a>
        <a href="perfil.php">👤 Meu Perfil</a>
        <a href="perfil.php">⚙ Configurações</a>
    </div>

    <!-- SEU IMPACTO -->
    <div class="sidebar-card">
        <h3>Seu Impacto</h3>

        <p>
            <strong>R$ <?php echo number_format($totalDoado, 2, ',', '.'); ?></strong>
            doados
        </p>

        <p>
            <strong><?php echo $campanhasApoiadas; ?></strong>
            campanhas apoiadas
        </p>

        <p>
            <strong><?php echo $ongsFavoritas; ?></strong>
            ONGs favoritas
        </p>
    </div>
    </aside>

    <!-- FEED -->
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

    <!-- LATERAL DIREITA -->
    <aside class="right-panel">
    <div class="sidebar-card">
        <h3>Campanhas em Destaque</h3>

        <div class="campaign">
        <img src="https://images.unsplash.com/photo-1509099836639-18ba1795216d?w=80&h=80&fit=crop" alt="Campanha">
        <div>
            <strong>Água para Todos</strong>
            <p>72% da meta alcançada</p>
        </div>
        </div>

        <div class="campaign">
        <img src="https://images.unsplash.com/photo-1529390079861-591de354faf5?w=80&h=80&fit=crop" alt="Campanha">
        <div>
            <strong>Biblioteca Comunitária</strong>
            <p>R$ 12.400 arrecadados</p>
        </div>
        </div>

        <div class="campaign">
        <img src="https://images.unsplash.com/photo-1513258496099-48168024aec0?w=80&h=80&fit=crop" alt="Campanha">
        <div>
            <strong>Educação que Transforma</strong>
            <p>Faltam 18 dias</p>
        </div>
        </div>
    </div>
    </aside>

</div>
<script src="../js/inicialPage.js?v=5"></script>
</body>
</html>
