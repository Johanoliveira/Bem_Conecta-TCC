<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/conexao.php";

$_hdrUsuarioId = (int) ($_SESSION["usuario_id"] ?? 0);
$_hdrFoto = $_SESSION["usuario_foto"] ?? "img/jpg/ftPerfil.jpg";
$_hdrNivel = 1;
$_hdrEhOng = false;

if ($_hdrUsuarioId > 0 && isset($conexao)) {
    $_stmtHdr = $conexao->prepare("
        SELECT mu.fotoPerfil, mu.nivelDeSeguranca, o.idONG
        FROM MoldeUsuario mu
        LEFT JOIN ONGs o ON o.idMoldeUsuario = mu.idMUsuario
        WHERE mu.idMUsuario = ?
        LIMIT 1
    ");
    if ($_stmtHdr) {
        $_stmtHdr->bind_param("i", $_hdrUsuarioId);
        $_stmtHdr->execute();
        $_dadosHdr = $_stmtHdr->get_result()->fetch_assoc();
        $_stmtHdr->close();

        if ($_dadosHdr) {
            $_hdrNivel = (int) ($_dadosHdr["nivelDeSeguranca"] ?? 1);
            $_hdrEhOng = ($_dadosHdr["idONG"] !== null);
            if (!empty($_dadosHdr["fotoPerfil"]) && !in_array($_dadosHdr["fotoPerfil"], ["perfil.png", "img/perfil/perfil.png"], true)) {
                $_hdrFoto = $_dadosHdr["fotoPerfil"];
                $_SESSION["usuario_foto"] = $_hdrFoto;
            }
        }
    }
}

if (empty($_hdrFoto) || in_array($_hdrFoto, ["perfil.png", "img/perfil/perfil.png"], true)) {
    $_hdrFoto = "img/jpg/ftPerfil.jpg";
}

$_hdrPaginaAtual = basename($_SERVER["PHP_SELF"]);
?>
<header class="topbar">
    <a href="inicialPage.php" class="logos" title="Ir para o Início">
        <div class="logo-foto">
            <img src="../img/logo/logo.webp" loading="lazy" alt="Logo Bem Conecta">
        </div>
        <div class="logo-escrita">
            <img src="../img/logo/escrita.png" loading="lazy" alt="Bem Conecta">
        </div>
    </a>

    <nav class="menu" id="topMenu">
        <a href="inicialPage.php" class="<?php echo $_hdrPaginaAtual === 'inicialPage.php' ? 'ativo' : ''; ?>">
            Início
        </a>
        <?php if ($_hdrNivel === 3): ?>
            <a href="painelAdmin.php" class="<?php echo $_hdrPaginaAtual === 'painelAdmin.php' ? 'ativo' : ''; ?>">
                🛡️ Painel Admin
            </a>
        <?php endif; ?>
        <a href="campanhas.php" class="<?php echo in_array($_hdrPaginaAtual, ['campanhas.php', 'criarCampanha.php'], true) ? 'ativo' : ''; ?>">
            Campanhas
        </a>
        <a href="ongs.php" class="<?php echo in_array($_hdrPaginaAtual, ['ongs.php', 'perfilONG.php'], true) ? 'ativo' : ''; ?>">
            ONGs
        </a>
        <?php if ($_hdrEhOng): ?>
            <a href="minhasArrecadacoes.php" class="<?php echo $_hdrPaginaAtual === 'minhasArrecadacoes.php' ? 'ativo' : ''; ?>">
                Minhas Arrecadações
            </a>
        <?php else: ?>
            <a href="minhasDoacoes.php" class="<?php echo $_hdrPaginaAtual === 'minhasDoacoes.php' ? 'ativo' : ''; ?>">
                Minhas Doações
            </a>
        <?php endif; ?>
    </nav>

    <div class="top-actions">
        <?php if ($_hdrNivel >= 2): ?>
            <a href="criarPost.php" class="ong-quick-btn">+ Post</a>
            <a href="criarCampanha.php" class="ong-quick-btn">+ Campanha</a>
        <?php endif; ?>

        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Pesquisar ONGs, posts...">
            <button type="button" class="search-btn" id="searchBtn" title="Pesquisar">🔍</button>
        </div>

        <button
            type="button"
            class="profile-btn <?php echo $_hdrPaginaAtual === 'perfil.php' ? 'ativo' : ''; ?>"
            onclick="window.location.href='perfil.php'"
        >
            <img src="../<?php echo htmlspecialchars($_hdrFoto, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto de perfil">
            Meu Perfil
        </button>

        <a href="logout.php" class="logout-btn" title="Encerrar sessão">
            Sair
        </a>

        <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Abrir menu">
            ☰
        </button>
    </div>
</header>
<script src="../js/header.js"></script>