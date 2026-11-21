<?php

session_start();

require_once "conexao.php";

// Verifica se existe usuário logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

$idUsuario = (int) $_SESSION["usuario_id"];

// =====================================
// SALVAR EDIÇÃO DO PERFIL NO BANCO
// =====================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["acao"]) && $_POST["acao"] === "editar_perfil") {
    header("Content-Type: application/json; charset=utf-8");

    $novoNome = trim($_POST["nome"] ?? "");
    $novoTelefone = trim($_POST["telefone"] ?? "");

    if ($novoNome === "") {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Erro ao salvar: o campo Nome completo não pode ficar vazio."
        ]);
        exit;
    }

    try {
        $sqlUpdate = "UPDATE MoldeUsuario SET nome = ?, telefone = ? WHERE idMUsuario = ?";
        $stmtUpdate = $conexao->prepare($sqlUpdate);

        if (!$stmtUpdate) {
            throw new Exception($conexao->error);
        }

        $stmtUpdate->bind_param("ssi", $novoNome, $novoTelefone, $idUsuario);

        if (!$stmtUpdate->execute()) {
            throw new Exception($stmtUpdate->error);
        }

        $stmtUpdate->close();

        if (isset($_SESSION["nome"])) {
            $_SESSION["nome"] = $novoNome;
        }

        echo json_encode([
            "sucesso" => true,
            "mensagem" => "Dados atualizados com sucesso!"
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Erro ao salvar no banco de dados: " . $e->getMessage()
        ]);
    }
    exit;
}

// Busca os dados do usuário
$sql = "SELECT idMUsuario, nome, email, telefone, fotoPerfil, nivelDeSeguranca
        FROM MoldeUsuario
        WHERE idMUsuario = ?";

$stmt = $conexao->prepare($sql);

if (!$stmt) {
    die("Erro ao preparar consulta: " . $conexao->error);
}

$stmt->bind_param("i", $idUsuario);
$stmt->execute();

$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();
$stmt->close();

// Verifica se o usuário existe
if (!$usuario) {
    session_destroy();
    header("Location: ../html/login.html");
    exit;
}

$nivel = (int) ($usuario["nivelDeSeguranca"] ?? 1);

// =====================================
// ATIVIDADES RECENTES
// =====================================
$sqlAtividades = "
    SELECT *
    FROM (
        SELECT
            'doacao' AS tipo,
            'Você realizou uma doação' AS titulo,
            CONCAT(
                'R$ ',
                REPLACE(FORMAT(d.valor, 2), '.', ','),
                ' para a campanha ',
                c.titulo
            ) AS descricao,
            d.dataDoacao AS dataAtividade
        FROM doacoes d
        INNER JOIN campanhas c
            ON d.idCampanha = c.idCampanha
        INNER JOIN UsuarioComum uc
            ON d.idUsuarioComum = uc.idUsuarioComum
        WHERE uc.idMoldeUsuario = ?

        UNION ALL

        SELECT
            'favorito' AS tipo,
            'Você adicionou uma ONG aos favoritos' AS titulo,
            mu.nome AS descricao,
            f.dataFavorito AS dataAtividade
        FROM favoritos f
        INNER JOIN ONGs o
            ON f.idONG = o.idONG
        INNER JOIN MoldeUsuario mu
            ON o.idMoldeUsuario = mu.idMUsuario
        INNER JOIN UsuarioComum uc
            ON f.idUsuarioComum = uc.idUsuarioComum
        WHERE uc.idMoldeUsuario = ?
    ) AS atividades_recentes

    ORDER BY dataAtividade DESC
    LIMIT 10
";

$stmtAtividades = $conexao->prepare($sqlAtividades);

if (!$stmtAtividades) {
    die("Erro ao preparar atividades recentes: " . $conexao->error);
}

$idParam1 = (int) $idUsuario;
$idParam2 = (int) $idUsuario;

$stmtAtividades->bind_param(
    "ii",
    $idParam1,
    $idParam2
);

$stmtAtividades->execute();
$resultadoAtividades = $stmtAtividades->get_result();

$atividadesRecentes = [];
while ($atividade = $resultadoAtividades->fetch_assoc()) {
    $atividadesRecentes[] = $atividade;
}

$stmtAtividades->close();

// Dados
$nome = $usuario["nome"] ?: "-";
$email = $usuario["email"] ?: "-";
$telefone = $usuario["telefone"] ?: "-";
$foto = $usuario["fotoPerfil"];

if (empty($foto) || in_array($foto, ["perfil.png", "img/perfil/perfil.png"], true)) {
    $foto = "img/jpg/ftPerfil.jpg";
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Bem Conecta</title>

    <link rel="stylesheet" href="../css/perfil.css?v=2">

    <!-- Ícones -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <!-- CABEÇALHO -->
    <header class="topo">

        <div class="logos">
            <div class="logo-foto">
                <img src="../img/logo/logo.webp" loading="lazy" title="logo">
            </div>
            <div class="logo-escrita">
                <img src="../img/logo/escrita.png" loading="lazy" title="logo">
            </div>
        </div>

        <nav class="menu-superior">
            <a href="../php/inicialPage.php">Início</a>
            <a href="#">Projetos</a>
            <a href="#">ONGs</a>
            <a href="#">Doações</a>
        </nav>

        <div class="usuario-topo">
            <button class="botao-pesquisa">
                🔍
            </button>
        </div>

    </header>

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="pagina">

        <!-- MENU LATERAL -->
        <aside class="sidebar">

            <div class="menu-lateral">

                <h2>Menu</h2>

                <a href="../php/inicialPage.php">
                    <span>🏠</span>
                    Página Inicial
                </a>

                <a href="#">
                    <span>💗</span>
                    Minhas Doações
                </a>

                <a href="#">
                    <span>📌</span>
                    ONGs Favoritas
                </a>

                <a href="#">
                    <span>📄</span>
                    Campanhas
                </a>

                <a href="#" class="ativo">
                    <span>👤</span>
                    Meu Perfil
                </a>

                <a href="#">
                    <span>⚙️</span>
                    Configurações
                </a>

            </div>

            <!-- só desaparece para usuarios comuns -->
            <?php if ($nivel >= 2): ?>
                <div class="meus-posts">
                    <a href="#">
                        Meus Posts
                    </a>
                </div>
            <?php endif; ?>

        </aside>

        <!-- ÁREA DO PERFIL -->
        <section class="conteudo-perfil">

            <!-- TÍTULO -->
            <div class="titulo-pagina">
                <h1>Meu Perfil</h1>
                <p>Gerencie suas informações e acompanhe seu impacto.</p>
            </div>

            <!-- CARD PRINCIPAL -->
            <div class="card-perfil">

                <div class="perfil-cabecalho">

                    <div class="foto-perfil">
                        <img
                            id="fotoUsuario"
                            src="../<?php echo htmlspecialchars($foto); ?>"
                            alt="Foto de perfil"
                        >

                        <button class="editar-foto" type="button" onclick="abrirPopupFoto()">
                            ✎
                        </button>
                    </div>

                    <div class="informacoes-principais">

                        <h2 id="nomeUsuario">
                            <?php echo htmlspecialchars($nome); ?>
                        </h2>

                        <p class="email" id="emailUsuario">
                            <i class="fa-solid fa-envelope"></i>
                            <?php echo htmlspecialchars($email); ?>
                        </p>

                        <p class="membro">
                            <i class="fa-solid fa-calendar"></i>
                            Membro desde
                            <span id="dataCriacao">-</span>
                        </p>

                    </div>

                    <div class="acoes-perfil">
                        <button type="button" class="botao-editar" id="btnEditarPerfil">
                            <i class="fa-solid fa-pen"></i>
                            Editar Perfil
                        </button>
                        <button type="button" class="botao-cancelar" id="btnCancelarEdicao" style="display: none;">
                            Cancelar
                        </button>
                    </div>

                </div>

                <hr>

                <!-- INFORMAÇÕES PESSOAIS -->
                <div class="secao">

                    <h2>Informações Pessoais</h2>

                    <div class="grid-informacoes">

                        <div class="campo" id="campoNome">
                            <span>Nome completo</span>
                            <p id="nomeCompleto">
                                <?php echo htmlspecialchars($nome); ?>
                            </p>
                            <input
                                type="text"
                                id="inputNome"
                                class="input-edicao"
                                style="display: none;"
                                value="<?php echo htmlspecialchars($nome === '-' ? '' : $nome); ?>"
                                placeholder="Digite seu nome completo"
                            >
                        </div>

                        <div class="campo" id="campoEmail">
                            <span>E-mail <small class="aviso-bloqueado" id="avisoEmail" style="display: none;">(não editável)</small></span>
                            <p class="email">
                                <i class="fa-solid fa-envelope"></i>
                                <?php echo htmlspecialchars($email); ?>
                            </p>
                        </div>

                        <div class="campo" id="campoTelefone">
                            <span>Telefone</span>
                            <p id="telefoneUsuario">
                                <?php echo htmlspecialchars($telefone); ?>
                            </p>
                            <input
                                type="text"
                                id="inputTelefone"
                                class="input-edicao"
                                style="display: none;"
                                value="<?php echo htmlspecialchars($telefone === '-' ? '' : $telefone); ?>"
                                placeholder="(00) 00000-0000"
                            >
                        </div>

                        <div class="campo" id="campoLocalizacao">
                            <span>Localização</span>
                            <p>São Paulo, SP</p>
                        </div>

                    </div>

                </div>

                <!-- INTERESSES -->
                <div class="secao interesses">
                    <h2>Causas que você apoia</h2>
                    <div class="tags" id="listaCausas">
                        <span>-</span>
                    </div>
                </div>

            </div>

            <!-- ESTATÍSTICAS -->
            <div class="estatisticas">

                <div class="card-estatistica">
                    <div class="icone verde">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                    </div>

                    <div>
                        <span>Total Doado</span>
                        <h2 id="totalDoadoEstatistica">-</h2>
                    </div>
                </div>

                <div class="card-estatistica">
                    <div class="icone">
                        <i class="fa-solid fa-bullseye"></i>
                    </div>

                    <div>
                        <span>Campanhas</span>
                        <h2 id="campanhasEstatistica">-</h2>
                    </div>
                </div>

                <div class="card-estatistica">
                    <div class="icone">
                        <i class="fa-solid fa-heart"></i>
                    </div>

                    <div>
                        <span>ONGs Favoritas</span>
                        <h2 id="ongsEstatistica">-</h2>
                    </div>
                </div>

            </div>

            <!-- ATIVIDADE RECENTE -->
            <div class="atividade">

                <h2>Atividade Recente</h2>

                <?php if (empty($atividadesRecentes)): ?>

                    <div class="atividade-item">

                        <div class="atividade-icone">
                            📌
                        </div>

                        <div>
                            <h3>Nenhuma atividade recente</h3>
                            <p>Suas ações aparecerão aqui.</p>
                        </div>

                    </div>

                <?php else: ?>

                    <?php foreach ($atividadesRecentes as $atividade): ?>

                        <?php
                        if ($atividade["tipo"] === "doacao") {
                            $icone = "❤️";
                        } elseif ($atividade["tipo"] === "favorito") {
                            $icone = "⭐";
                        } else {
                            $icone = "📌";
                        }

                        $dataAtividade = new DateTime($atividade["dataAtividade"]);
                        $agora = new DateTime();
                        $diferenca = $agora->diff($dataAtividade);

                        if ($diferenca->days == 0) {
                            $tempo = "Hoje";
                        } elseif ($diferenca->days == 1) {
                            $tempo = "Ontem";
                        } else {
                            $tempo = $diferenca->days . " dias";
                        }
                        ?>

                        <div class="atividade-item">

                            <div class="atividade-icone">
                                <?= $icone ?>
                            </div>

                            <div>
                                <h3>
                                    <?= htmlspecialchars($atividade["titulo"]) ?>
                                </h3>

                                <p>
                                    <?= htmlspecialchars($atividade["descricao"]) ?>
                                </p>
                            </div>

                            <span>
                                <?= htmlspecialchars($tempo) ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </section>

    </main>

    <div id="popupFoto" class="popup-foto">
        <div class="popup-conteudo">

            <button class="fechar-popup" type="button" onclick="fecharPopupFoto()">
                &times;
            </button>

            <h2>Alterar foto de perfil</h2>

            <form id="formFoto" enctype="multipart/form-data">

                <div class="preview-foto">
                    <span>+</span>
                </div>

                <label for="fotoPerfil" class="selecionar-foto">
                    Escolher foto
                </label>

                <input
                    type="file"
                    id="fotoPerfil"
                    name="fotoPerfil"
                    accept="image/jpeg,image/png,image/webp"
                    required
                >

                <div class="botoes-popup">

                    <button
                        type="button"
                        class="cancelar-foto"
                        onclick="fecharPopupFoto()">
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="salvar-foto">
                        Salvar foto
                    </button>

                </div>

            </form>

        </div>
    </div>

    <script src="../js/perfil.js?v=2"></script>

</body>

</html>