<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/conexao.php";

$_sbUsuarioId = (int) ($_SESSION["usuario_id"] ?? 0);
$_sbTotalDoado = 0.0;
$_sbCampanhasApoiadas = 0;
$_sbOngsFavoritas = 0;

$_sbEhUsuarioComum = false;
if ($_sbUsuarioId > 0 && isset($conexao)) {
    $stmtSbTipo = $conexao->prepare("SELECT 1 FROM UsuarioComum WHERE idMoldeUsuario = ? LIMIT 1");
    if ($stmtSbTipo) {
        $stmtSbTipo->bind_param("i", $_sbUsuarioId);
        $stmtSbTipo->execute();
        $_sbEhUsuarioComum = $stmtSbTipo->get_result()->num_rows > 0;
        $stmtSbTipo->close();
    }
}

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

        <?php if (($_hdrNivel ?? 0) === 2 && ($_hdrEhOng ?? false)): ?>
            <a href="minhasArrecadacoes.php" class="<?php echo $_sbPaginaAtual === 'minhasArrecadacoes.php' ? 'ativo' : ''; ?>" title="Minhas Arrecadações">
                <span class="sb-icone">💰</span>
                <span class="sb-texto">Minhas Arrecadações</span>
            </a>
        <?php endif; ?>

        <?php if ((int) ($_SESSION["nivel_seguranca"] ?? 0) === 3 || ($_hdrNivel ?? 0) === 3): ?>
         <a href="painelAdmin.php" class="<?php echo $_sbPaginaAtual === 'painelAdmin.php' ? 'ativo' : ''; ?>" title="Painel Admin">
             <span class="sb-icone">🛡️</span>
             <span class="sb-texto">Painel Admin</span>
         </a>
        <?php endif; ?>

        <a href="minhasDoacoes.php" class="<?php echo $_sbPaginaAtual === 'minhasDoacoes.php' ? 'ativo' : ''; ?>" title="Minhas Doações">
            <span class="sb-icone">❤️</span>
            <span class="sb-texto">Minhas Doações</span>
        </a>

        <?php if ($_sbEhUsuarioComum): ?>
            <a href="ongs.php?favoritas=1" class="<?php echo in_array($_sbPaginaAtual, ['ongs.php', 'perfilONG.php'], true) ? 'ativo' : ''; ?>" title="ONGs Favoritas">
                <span class="sb-icone">📌</span>
                <span class="sb-texto">ONGs Favoritas</span>
            </a>
        <?php else: ?>
            <a href="ongs.php" class="<?php echo in_array($_sbPaginaAtual, ['ongs.php', 'perfilONG.php'], true) ? 'ativo' : ''; ?>" title="ONGs Parceiras">
                <span class="sb-icone">🏢</span>
                <span class="sb-texto">ONGs Parceiras</span>
            </a>
        <?php endif; ?>

        <a href="campanhas.php" class="<?php echo in_array($_sbPaginaAtual, ['campanhas.php', 'criarCampanha.php'], true) ? 'ativo' : ''; ?>" title="Campanhas">
            <span class="sb-icone">📄</span>
            <span class="sb-texto">Campanhas</span>
        </a>

        <a href="perfil.php" class="<?php echo $_sbPaginaAtual === 'perfil.php' ? 'ativo' : ''; ?>" title="Meu Perfil">
            <span class="sb-icone">👤</span>
            <span class="sb-texto">Meu Perfil</span>
        </a>

        <a href="#configuracoes" id="btnAbrirConfiguracoes" title="Configurações">
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
        <?php if ($_sbEhUsuarioComum): ?>
            <p>
                <strong id="sbContadorFavoritas"><?php echo $_sbOngsFavoritas; ?></strong>
                ONGs favoritas
            </p>
        <?php endif; ?>
    </div>
</aside>

<!-- MODAL DE CONFIGURAÇÕES E EXCLUSÃO DE CONTA -->
<div class="bc-config-overlay" id="bcConfigOverlay" aria-hidden="true">
    <div class="bc-config-modal" role="dialog" aria-modal="true" aria-labelledby="bcConfigTitulo">
        <div class="bc-config-header">
            <h2 id="bcConfigTitulo">⚙️ Configurações da Conta</h2>
            <button type="button" class="bc-config-fechar" id="bcConfigFechar" title="Fechar">✕</button>
        </div>

        <div class="bc-config-bloco">
            <h3>👤 Perfil e Dados Pessoais</h3>
            <p>Altere seu nome, foto de perfil, telefone ou endereço cadastrado.</p>
            <a href="perfil.php" class="bc-config-btn-secundario">✏️ Ir para Meu Perfil</a>
        </div>

        <div class="bc-config-bloco bc-config-perigo">
            <h3>⚠️ Excluir Conta</h3>
            <p>
                Esta ação é <strong>permanente</strong> e apagará seus dados, histórico e publicações vinculadas no Bem Conecta.
            </p>

            <button type="button" class="bc-config-btn-perigo" id="bcBtnMostrarConfirmacao">
                🗑️ Excluir Minha Conta
            </button>

            <form id="bcFormExcluirConta" class="bc-config-confirmacao" style="display: none;">
                <label for="bcSenhaExcluir">Digite sua senha atual para confirmar:</label>
                <input
                    type="password"
                    id="bcSenhaExcluir"
                    name="senha"
                    placeholder="Sua senha atual"
                    required
                    autocomplete="current-password"
                >
                <p class="bc-config-erro" id="bcErroExcluir"></p>

                <div class="bc-config-acoes-form">
                    <button type="button" class="bc-config-btn-cancelar" id="bcBtnCancelarExclusao">
                        Cancelar
                    </button>
                    <button type="submit" class="bc-config-btn-confirmar" id="bcBtnConfirmarExclusao">
                        Confirmar Exclusão
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.bc-config-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.62);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 9999;
}

.bc-config-overlay.aberto {
    display: flex;
}

.bc-config-modal {
    width: 100%;
    max-width: 480px;
    border-radius: 22px;
    padding: 26px;
    color: #ffffff;
    background: linear-gradient(
        145deg,
        rgba(35, 48, 38, 0.88),
        rgba(20, 28, 22, 0.92)
    );
    backdrop-filter: blur(22px) saturate(180%);
    -webkit-backdrop-filter: blur(22px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.28);
    box-shadow:
        0 24px 60px rgba(0, 0, 0, 0.45),
        inset 0 1px 1px rgba(255, 255, 255, 0.45);
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.bc-config-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.bc-config-header h2 {
    font-size: 1.35rem;
    color: #ffffff;
}

.bc-config-fechar {
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #ffffff;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 700;
}

.bc-config-fechar:hover {
    background: rgba(255, 255, 255, 0.26);
}

.bc-config-bloco {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 16px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.bc-config-bloco h3 {
    font-size: 1.05rem;
    color: #7dff9e;
}

.bc-config-bloco p {
    font-size: 0.92rem;
    color: rgba(255, 255, 255, 0.88);
    line-height: 1.45;
}

.bc-config-bloco.bc-config-perigo {
    background: rgba(217, 48, 37, 0.14);
    border-color: rgba(255, 107, 107, 0.42);
}

.bc-config-bloco.bc-config-perigo h3 {
    color: #ff8a80;
}

.bc-config-btn-secundario {
    display: inline-block;
    text-align: center;
    text-decoration: none;
    background: #1ea342;
    color: #ffffff;
    font-weight: 700;
    font-size: 0.94rem;
    padding: 11px 16px;
    border-radius: 10px;
    transition: background 0.2s ease;
}

.bc-config-btn-secundario:hover {
    background: #168232;
}

.bc-config-btn-perigo {
    background: #d93025;
    color: #ffffff;
    border: none;
    font-weight: 700;
    font-size: 0.95rem;
    padding: 12px 16px;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.2s ease;
}

.bc-config-btn-perigo:hover {
    background: #b5241b;
}

.bc-config-confirmacao {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 4px;
}

.bc-config-confirmacao label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #ffffff;
}

.bc-config-confirmacao input {
    width: 100%;
    padding: 11px 14px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.95);
    color: #1f2922;
    font-size: 0.95rem;
    outline: none;
}

.bc-config-erro {
    color: #ff9e96 !important;
    font-size: 0.88rem !important;
    font-weight: 600;
    min-height: 18px;
}

.bc-config-acoes-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.bc-config-btn-cancelar,
.bc-config-btn-confirmar {
    padding: 11px 14px;
    border-radius: 10px;
    border: none;
    font-weight: 700;
    font-size: 0.92rem;
    cursor: pointer;
}

.bc-config-btn-cancelar {
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
}

.bc-config-btn-confirmar {
    background: #d93025;
    color: #ffffff;
}

.bc-config-btn-confirmar:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
</style>

<script src="../js/sidebar.js"></script>
<script>
(function () {
    const btnAbrir = document.getElementById("btnAbrirConfiguracoes");
    const overlay = document.getElementById("bcConfigOverlay");
    const btnFechar = document.getElementById("bcConfigFechar");
    const btnMostrarConfirmacao = document.getElementById("bcBtnMostrarConfirmacao");
    const formExcluir = document.getElementById("bcFormExcluirConta");
    const btnCancelar = document.getElementById("bcBtnCancelarExclusao");
    const btnConfirmar = document.getElementById("bcBtnConfirmarExclusao");
    const inputSenha = document.getElementById("bcSenhaExcluir");
    const msgErro = document.getElementById("bcErroExcluir");

    if (!btnAbrir || !overlay) return;

    function abrirModal() {
        overlay.classList.add("aberto");
        overlay.setAttribute("aria-hidden", "false");
    }

    function fecharModal() {
        overlay.classList.remove("aberto");
        overlay.setAttribute("aria-hidden", "true");
        if (formExcluir) formExcluir.style.display = "none";
        if (btnMostrarConfirmacao) btnMostrarConfirmacao.style.display = "block";
        if (inputSenha) inputSenha.value = "";
        if (msgErro) msgErro.textContent = "";
    }

    btnAbrir.addEventListener("click", function (e) {
        e.preventDefault();
        abrirModal();
    });

    if (window.location.hash === "#configuracoes") {
        abrirModal();
    }

    btnFechar?.addEventListener("click", fecharModal);

    overlay.addEventListener("click", function (e) {
        if (e.target === overlay) {
            fecharModal();
        }
    });

    btnMostrarConfirmacao?.addEventListener("click", function () {
        btnMostrarConfirmacao.style.display = "none";
        formExcluir.style.display = "flex";
        inputSenha.focus();
    });

    btnCancelar?.addEventListener("click", function () {
        formExcluir.style.display = "none";
        btnMostrarConfirmacao.style.display = "block";
        inputSenha.value = "";
        msgErro.textContent = "";
    });

    formExcluir?.addEventListener("submit", async function (e) {
        e.preventDefault();
        msgErro.textContent = "";

        const senha = inputSenha.value.trim();
        if (!senha) {
            msgErro.textContent = "Digite sua senha para confirmar.";
            return;
        }

        if (!confirm("Tem certeza absoluta? Sua conta será excluída permanentemente.")) {
            return;
        }

        btnConfirmar.disabled = true;
        btnConfirmar.textContent = "Excluindo...";

        try {
            const resp = await fetch("excluirConta.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ senha })
            });

            const dados = await resp.json();

            if (!resp.ok || !dados.sucesso) {
                msgErro.textContent = dados.erro || "Erro ao excluir a conta.";
                btnConfirmar.disabled = false;
                btnConfirmar.textContent = "Confirmar Exclusão";
                return;
            }

            alert("Sua conta foi excluída com sucesso.");
            window.location.href = "../html/login.html";
        } catch (err) {
            msgErro.textContent = "Erro de conexão ao tentar excluir a conta.";
            btnConfirmar.disabled = false;
            btnConfirmar.textContent = "Confirmar Exclusão";
        }
    });
})();
</script>