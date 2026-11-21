<?php
ob_start();

function obterDestinoVoltar() {
    $arquivoAtual = basename($_SERVER['PHP_SELF'] ?? '');

    if ($arquivoAtual === 'cadastroDoador.php') {
        return ['../html/cadastroDoador.html', 'Voltar para o Cadastro de Doador'];
    }
    if ($arquivoAtual === 'cadastroONG.php') {
        return ['../html/cadastroONG.html', 'Voltar para o Cadastro da ONG'];
    }
    return ['../html/login.html', 'Voltar para o Login'];
}

function exibirErro($mensagem, $linkVoltar = null, $textoBotao = null) {
    if (ob_get_level() > 0) {
        ob_clean();
    }

    [$linkPadrao, $textoPadrao] = obterDestinoVoltar();
    $link = $linkVoltar ?: $linkPadrao;
    $botao = $textoBotao ?: $textoPadrao;
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erro - Bem Conecta</title>
        <link rel="stylesheet" href="../css/login.css">
        <style>
            .erro-card {
                text-align: center;
            }
            .erro-icone {
                font-size: 3rem;
                color: #ff6b6b;
                margin-bottom: 12px;
            }
            .erro-card h1 {
                margin-bottom: 16px;
                font-size: 1.7em;
            }
            .erro-mensagem {
                color: #f5f5f5;
                font-size: 1.05em;
                line-height: 1.5;
                margin-bottom: 28px;
            }
            a.login-btn {
                display: block;
                width: 100%;
                text-decoration: none;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <header>
            <div class="logos">
                <div class="logo-foto">
                    <img src="../img/logo/logo.png" alt="Logo Bem Conecta">
                </div>
                <div class="logo-escrita">
                    <img src="../img/logo/escrita.png" alt="Bem Conecta">
                </div>
            </div>
            <div class="nav-links">
                <a href="../index.html">Início</a>
            </div>
        </header>

        <main class="login-container">
            <div class="login-card erro-card">
                <div class="erro-icone">&#9888;</div>
                <h1>Ops! Algo deu errado</h1>
                <p class="erro-mensagem"><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
                <a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>" class="login-btn">
                    <?php echo htmlspecialchars($botao, ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <div class="signup-section">
                    <p>Ainda não tem conta? <a href="../html/cadastro.html">Cadastre-se</a></p>
                </div>
            </div>
        </main>

        <footer>
            <p>&copy; 2026 Bem Conecta. Todos os direitos reservados.</p>
        </footer>
    </body>
    </html>
    <?php
    exit;
}

set_exception_handler(function ($erro) {
    $msg = $erro->getMessage();
    if (stripos($msg, 'Duplicate entry') !== false) {
        exibirErro("Já existe um cadastro com este e-mail, CPF ou CNPJ.");
    }
    exibirErro("Erro ao processar seus dados: " . $msg);
});