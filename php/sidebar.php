<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/conexao.php";

$_sbUsuarioId = (int) ($_SESSION["usuario_id"] ?? 0);
$_sbTotalDoado = 0.0;
$_sbCampanhasApoiadas = 0;
$_sbOngsFavoritas = 0;

if ($_sbUsuarioId > 0 && isset($conexao)) {
    $sqlImpacto = "
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

    $stmtImpacto = $conexao->prepare($sqlImpacto);
    if ($stmtImpacto) {
        $stmtImpacto->bind_param("iii", $_sbUsuarioId, $_sbUsuarioId, $_sbUsuarioId);
        if ($stmtImpacto->execute()) {
            $dadosImpacto = $stmtImpacto->get_result()->fetch_assoc();
            if ($dadosImpacto) {
                $_sbTotalDoado = (float) $dadosImpacto["totalDoado"];
                $_sbCampanhasApoiadas = (int) $dadosImpacto["campanhasApoiadas"];
                $_sbOngsFavoritas = (int) $dadosImpacto["ongsFavoritas"];
            }
        }
        $stmtImpacto->close();
    }
}

$_sbPaginaAtual = basename($_SERVER["PHP_SELF"]);
?>
<aside class="sidebar" id="sidebarLateral">
    <div class="sidebar-card">
        <div class="sidebar-topo">
            <h3>Menu</h3>
            <button type="button" class="btn-minimizar-sidebar" id="btnMinimizarSidebar" title="Minimizar menu">◀</button>
        </div>

        <a href="inicialPage.php" class="<?php echo $_sbPaginaAtual === 'inicialPage.php' ? 'ativo' : ''; ?>" title="Página Inicial">
            <span class="sb-icone">🏠</span>
            <span class="sb-texto">Página Inicial</span>
        </a>

        <a href="minhasDoacoes.php" class="<?php echo $_sbPaginaAtual === 'minhasDoacoes.php' ? 'ativo' : ''; ?>" title="Minhas Doações">
            <span class="sb-icone">❤️</span>
            <span class="sb-texto">Minhas Doações</span>
        </a>

        <a href="ongs.php" class="<?php echo in_array($_sbPaginaAtual, ['ongs.php', 'perfilONG.php'], true) ? 'ativo' : ''; ?>" title="ONGs Favoritas">
            <span class="sb-icone">📌</span>
            <span class="sb-texto">ONGs Favoritas</span>
        </a>

        <a href="campanhas.php" class="<?php echo in_array($_sbPaginaAtual, ['campanhas.php', 'criarCampanha.php'], true) ? 'ativo' : ''; ?>" title="Campanhas">
            <span class="sb-icone">📄</span>
            <span class="sb-texto">Campanhas</span>
        </a>

        <a href="perfil.php" class="<?php echo $_sbPaginaAtual === 'perfil.php' ? 'ativo' : ''; ?>" title="Meu Perfil">
            <span class="sb-icone">👤</span>
            <span class="sb-texto">Meu Perfil</span>
        </a>

        <a href="perfil.php#configuracoes" title="Configurações">
            <span class="sb-icone">⚙</span>
            <span class="sb-texto">Configurações</span>
        </a>
    </div>

    <div class="sidebar-card sidebar-impacto">
        <h3>Seu Impacto</h3>

        <p>
            <strong>R$ <?php echo number_format($_sbTotalDoado, 2, ',', '.'); ?></strong>
            doados
        </p>

        <p>
            <strong><?php echo $_sbCampanhasApoiadas; ?></strong>
            campanhas apoiadas
        </p>

        <p>
            <strong><?php echo $_sbOngsFavoritas; ?></strong>
            ONGs favoritas
        </p>
    </div>
</aside>
<script src="../js/sidebar.js"></script>