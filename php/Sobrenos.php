<?php
session_start();
 
$logado = isset($_SESSION["usuario_id"]);
$nome   = $logado ? $_SESSION["usuario_nome"] : null;
$foto   = $logado ? $_SESSION["usuario_foto"] : null;
?>
 
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem Conecta - Sobre Nós</title>
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
        <a href="inicialPage.php">Projetos</a>
        <a href="#">ONGs</a>
        <a href="#">Doações</a>
        <a href="sobreNos.php">Sobre Nós</a>
    </nav>
 
    <div class="top-actions">
 
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Pesquisar ONGs...">
            <button class="search-btn" id="searchBtn">🔍</button>
        </div>
 
        <?php if ($logado): ?>
        <button class="profile-btn" onclick="window.location.href='perfil.php'">
            <img src="../php/<?php echo htmlspecialchars($foto); ?>" alt="foto de perfil">
            Meu Perfil
        </button>
        <?php else: ?>
        <button class="profile-btn" onclick="window.location.href='login.html'">
            Entrar
        </button>
        <?php endif; ?>
 
    </div>
</header>
 
<!-- CONTEÚDO -->
<div class="layout">
 
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-card">
            <h3>Menu</h3>
            <a href="inicialPage.php">🏠 Página Inicial</a>
            <a href="#">❤️ Minhas Doações</a>
            <a href="#">📌 ONGs Favoritas</a>
            <a href="#">📄 Campanhas</a>
            <a href="#">🎯 Voluntariado</a>
            <a href="sobreNos.php">ℹ️ Sobre Nós</a>
        </div>
 
        <div class="sidebar-card">
            <h3>Fale Conosco</h3>
            <p>Dúvidas, sugestões ou parcerias?</p>
            <a href="mailto:contato@bemconecta.com.br">✉️ contato@bemconecta.com.br</a>
        </div>
    </aside>
 
    <!-- FEED / CONTEÚDO PRINCIPAL -->
    <main class="feed">
 
        <section class="welcome-card">
            <h2>Sobre o Bem Conecta</h2>
            <p>
                O Bem Conecta nasceu com um propósito simples: aproximar quem quer ajudar
                de quem mais precisa. Somos uma plataforma que conecta doadores diretamente
                a ONGs verificadas, garantindo que cada contribuição chegue de forma
                transparente até os projetos sociais que realmente transformam vidas.
            </p>
        </section>
 
        <section class="post-card">
            <div class="post-header">
                <h3>Nossa Missão</h3>
            </div>
            <p>
                Facilitar o encontro entre doadores e organizações sociais, oferecendo um
                caminho direto, seguro e transparente para que a doação chegue à ONG certa,
                sem intermediários e com total clareza sobre o destino de cada real doado.
            </p>
        </section>
 
        <section class="post-card">
            <div class="post-header">
                <h3>Como Funciona</h3>
            </div>
            <p>
                As ONGs cadastradas na plataforma publicam suas campanhas e projetos.
                Você escolhe a causa com a qual mais se identifica e doa diretamente para
                ela, acompanhando o progresso da arrecadação e o impacto gerado — sem taxas
                escondidas e sem burocracia.
            </p>
        </section>
 
        <section class="post-card">
            <div class="post-header">
                <h3>Nossos Valores</h3>
            </div>
            <p>
                <strong>Transparência:</strong> toda doação é rastreável, do doador até a ONG.<br>
                <strong>Confiança:</strong> apenas ONGs verificadas participam da plataforma.<br>
                <strong>Impacto direto:</strong> sem intermediários, sem desvio de finalidade.<br>
                <strong>Comunidade:</strong> conectamos pessoas dispostas a transformar realidades.
            </p>
        </section>
 
    </main>
 
    <!-- LATERAL DIREITA -->
    <aside class="right-panel">
        <div class="sidebar-card">
            <h3>Nosso Impacto</h3>
            <div class="campaign">
                <div>
                    <strong>Inifinitas</strong>
                    <p>ONGs parceiras</p>
                </div>
            </div>
            <div class="campaign">
                <div>
                    <strong>10 Bilhões</strong>
                    <p>doações realizadas</p>
                </div>
            </div>
            <div class="campaign">
                <div>
                    <strong>R$ 280 mil</strong>
                    <p>direcionados a projetos sociais</p>
                </div>
            </div>
        </div>
 
        <div class="sidebar-card">
            <h3>Quer Ajudar?</h3>
            <p>Conheça as campanhas ativas e escolha uma causa para apoiar hoje mesmo.</p>
            <a href="inicialPage.php">Ver Campanhas →</a>
        </div>
    </aside>
 
</div>
 
<script src="../js/inicialPage.js"></script>
</body>
</html>