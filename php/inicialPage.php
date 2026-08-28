<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.html");
    exit;
}

$nome = $_SESSION["usuario_nome"];
$foto = $_SESSION["usuario_foto"];
?>

<!DOCTYPE html>
<html lang="pt-BR">

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem Conecta - Início</title>
    <link rel="stylesheet" href="../css/inicialPage.css">
</head>

<body>
<!-- TOPO -->
<header class="topbar">
    <div class="logos">
        <div class="logo-foto">
            <img src="../img/logo/logo.webp" loading="lazy" alt="">
        </div>
        <div class="logo-escrita">
            <img src="../img/logo/escrita.png" loading="lazy" alt="">
        </div>
    </div>

    <nav class="menu">
    <a href="#">Projetos</a>
    <a href="#">ONGs</a>
    <a href="#">Doações</a>
    </nav>

    <div class="top-actions">

    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Pesquisar ONGs...">
        <button class="search-btn" id="searchBtn">🔍</button>
    </div>

    <button class="profile-btn" onclick="window.location.href='perfil.php'">
        <img src="../php/<?php echo htmlspecialchars($fotoPerfil); ?>" alt="img/jpg/ftPerfil.jpg">
        Meu Perfil
    </button>
    </div>
</header>

<!-- CONTEÚDO -->
<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
    <div class="sidebar-card">
        <h3>Menu</h3>

        <a href="#">🏠 Página Inicial</a>
        <a href="#">❤️ Minhas Doações</a>
        <a href="#">📌 ONGs Favoritas</a>
        <a href="#">📄 Campanhas</a>
        <a href="#">🎯 Voluntariado</a>
        <a href="#">👤 Meu Perfil</a>
        <a href="#">⚙ Configurações</a>
    </div>

    <div class="sidebar-card">
        <h3>Seu Impacto</h3>
        <p><strong>R$ 320,00</strong> doados</p>
        <p><strong>8</strong> campanhas apoiadas</p>
        <p><strong>3</strong> ONGs favoritas</p>
    </div>
    </aside>

    <!-- FEED -->
    <main class="feed">

    <section class="welcome-card">
        <h2>Bem-vindo de volta, <?php echo htmlspecialchars($nome); ?>!</h2>
        <p>Veja as campanhas mais recentes e continue apoiando projetos sociais que transformam vidas.</p>
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
<script src="../js/inicialPage.js"></script>
</body>
</html>
