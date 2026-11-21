
<?php
session_start();

// =========================================
// 1. VERIFICAR LOGIN
// =========================================

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.html");
    exit;
}

require_once __DIR__ . "/../php/conexao.php";

$usuarioId = (int) $_SESSION["usuario_id"];
$erro = "";

// =========================================
// 2. BUSCAR USUÁRIO, NÍVEL E ONG
// =========================================

$sqlUsuario = "
    SELECT
        mu.nivelDeSeguranca,
        o.idONG
    FROM MoldeUsuario mu
    LEFT JOIN ONGs o
        ON o.idMoldeUsuario = mu.idMUsuario
    WHERE mu.idMUsuario = ?
    LIMIT 1
";

$stmt = $conexao->prepare($sqlUsuario);

if (!$stmt) {
    error_log("Erro ao preparar consulta do usuário: " . $conexao->error);
    http_response_code(500);
    exit("Não foi possível carregar os dados do usuário.");
}

$stmt->bind_param("i", $usuarioId);
$stmt->execute();

$usuario = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$usuario) {
    http_response_code(403);
    exit("Usuário não encontrado.");
}

$nivel = (int) $usuario["nivelDeSeguranca"];

$idONG = $usuario["idONG"] !== null
    ? (int) $usuario["idONG"]
    : null;

// Usuários de nível 1 não podem criar posts.
if ($nivel === 1) {
    http_response_code(403);
    exit("Você não tem permissão para criar publicações.");
}

// =========================================
// 3. BUSCAR CAMPANHAS DISPONÍVEIS
// =========================================

$campanhas = [];

if ($nivel === 2 && $idONG !== null) {
    // ONGs só podem selecionar campanhas próprias.
    $sqlCampanhas = "
        SELECT idCampanha, titulo
        FROM campanhas
        WHERE idONG = ?
        ORDER BY dataInicio DESC
    ";

    $stmtCampanhas = $conexao->prepare($sqlCampanhas);
    $stmtCampanhas->bind_param("i", $idONG);
} elseif ($nivel === 3) {
    // Administradores podem selecionar campanhas de qualquer ONG.
    $sqlCampanhas = "
        SELECT idCampanha, titulo
        FROM campanhas
        ORDER BY dataInicio DESC
    ";

    $stmtCampanhas = $conexao->prepare($sqlCampanhas);
} else {
    $stmtCampanhas = null;
}

if ($stmtCampanhas) {
    $stmtCampanhas->execute();

    $resultadoCampanhas = $stmtCampanhas->get_result();

    while ($campanha = $resultadoCampanhas->fetch_assoc()) {
        $campanhas[] = $campanha;
    }

    $stmtCampanhas->close();
}

// =========================================
// 4. PROCESSAR FORMULÁRIO
// =========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $titulo = trim($_POST["titulo"] ?? "");
    $descricao = trim($_POST["descricao"] ?? "");
    $palavrasChave = trim($_POST["palavrasChave"] ?? "");
    $campanhaSelecionada = $_POST["idCampanha"] ?? "";

    // -----------------------------------------
    // 4.1 VALIDAR DADOS
    // -----------------------------------------

    if ($nivel === 2 && $idONG === null) {
        $erro = "Sua conta não está vinculada a uma ONG.";

    } elseif ($titulo === "" || $descricao === "") {
        $erro = "Preencha o título e a descrição.";

    } elseif (mb_strlen($titulo) > 70) {
        $erro = "O título deve ter no máximo 70 caracteres.";

    } elseif (mb_strlen($descricao) > 20000) {
        $erro = "A descrição deve ter no máximo 20.000 caracteres.";

    } elseif (mb_strlen($palavrasChave) > 255) {
        $erro = "As palavras-chave devem ter no máximo 255 caracteres.";

    } elseif (
        !isset($_FILES["imagem"]) ||
        $_FILES["imagem"]["error"] !== UPLOAD_ERR_OK
    ) {
        $erro = "Selecione uma imagem válida para a publicação.";

    } elseif ($_FILES["imagem"]["size"] > 5 * 1024 * 1024) {
        $erro = "A imagem deve ter no máximo 5 MB.";
    }

    // -----------------------------------------
    // 4.2 VALIDAR CAMPANHA SELECIONADA
    // -----------------------------------------

    $idCampanha = null;

    if ($erro === "" && $campanhaSelecionada !== "") {

        $idCampanha = filter_var(
            $campanhaSelecionada,
            FILTER_VALIDATE_INT
        );

        if ($idCampanha === false || $idCampanha < 1) {
            $erro = "Selecione uma campanha válida.";
        } else {

            if ($nivel === 2) {
                // ONG só pode vincular campanhas próprias.
                $sqlVerifica = "
                    SELECT idCampanha
                    FROM campanhas
                    WHERE idCampanha = ?
                      AND idONG = ?
                    LIMIT 1
                ";

                $stmtVerifica = $conexao->prepare($sqlVerifica);
                $stmtVerifica->bind_param(
                    "ii",
                    $idCampanha,
                    $idONG
                );

            } else {
                // Administrador pode vincular qualquer campanha existente.
                $sqlVerifica = "
                    SELECT idCampanha
                    FROM campanhas
                    WHERE idCampanha = ?
                    LIMIT 1
                ";

                $stmtVerifica = $conexao->prepare($sqlVerifica);
                $stmtVerifica->bind_param("i", $idCampanha);
            }

            $stmtVerifica->execute();

            $campanhaValida =
                $stmtVerifica->get_result()->num_rows > 0;

            $stmtVerifica->close();

            if (!$campanhaValida) {
                $erro = "A campanha selecionada não existe ou não está disponível para sua conta.";
            }
        }
    }

    // -----------------------------------------
    // 4.3 DEFINIR ONG RESPONSÁVEL PELO POST
    // -----------------------------------------

    $idONGPost = $idONG;

    if ($erro === "" && $nivel === 3 && $idONGPost === null) {

        // Para o administrador, utiliza a ONG da campanha
        // selecionada, quando houver.
        if ($idCampanha !== null) {
            $sqlONG = "
                SELECT idONG
                FROM campanhas
                WHERE idCampanha = ?
                LIMIT 1
            ";

            $stmtONG = $conexao->prepare($sqlONG);
            $stmtONG->bind_param("i", $idCampanha);
            $stmtONG->execute();

            $dadosONG = $stmtONG->get_result()->fetch_assoc();

            $stmtONG->close();

            if ($dadosONG) {
                $idONGPost = (int) $dadosONG["idONG"];
            }
        }

        if ($idONGPost === null) {
            $erro = "Para publicar sem uma campanha, sua conta precisa estar vinculada a uma ONG.";
        }
    }

    // -----------------------------------------
    // 4.4 VALIDAR E SALVAR IMAGEM
    // -----------------------------------------

    $caminhoImagem = null;

    if ($erro === "") {

        $temporario = $_FILES["imagem"]["tmp_name"];

        if (!is_uploaded_file($temporario)) {
            $erro = "O arquivo enviado é inválido.";
        } else {

            $tipo = (new finfo(FILEINFO_MIME_TYPE))
                ->file($temporario);

            $tiposPermitidos = [
                "image/jpeg" => "jpg",
                "image/png"  => "png",
                "image/webp" => "webp"
            ];

            $informacoesImagem = @getimagesize($temporario);

            if (
                !isset($tiposPermitidos[$tipo]) ||
                $informacoesImagem === false
            ) {
                $erro = "Formato inválido. Envie uma imagem JPG, PNG ou WEBP.";

            } else {

                $diretorio = __DIR__ . "/../img/posts/";

                if (
                    !is_dir($diretorio) &&
                    !mkdir($diretorio, 0755, true) &&
                    !is_dir($diretorio)
                ) {
                    $erro = "Não foi possível preparar a pasta de imagens.";
                }

                if ($erro === "") {

                    $nomeArquivo = bin2hex(random_bytes(16))
                        . "." . $tiposPermitidos[$tipo];

                    $destino = $diretorio . $nomeArquivo;

                    if (move_uploaded_file($temporario, $destino)) {
                        $caminhoImagem = "../img/posts/" . $nomeArquivo;
                    } else {
                        $erro = "Não foi possível salvar a imagem.";
                    }
                }
            }
        }
    }

    // -----------------------------------------
    // 4.5 INSERIR PUBLICAÇÃO NO BANCO
    // -----------------------------------------

    if ($erro === "") {

        $sqlPost = "
            INSERT INTO posts (
                idONG,
                idCampanha,
                titulo,
                conteudo,
                descricao,
                palavrasChave
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ";

        $stmtPost = $conexao->prepare($sqlPost);

        if (!$stmtPost) {

            error_log("Erro ao preparar publicação: " . $conexao->error);
            $erro = "Não foi possível preparar a publicação.";

        } else {

            $stmtPost->bind_param(
                "iissss",
                $idONGPost,
                $idCampanha,
                $titulo,
                $caminhoImagem,
                $descricao,
                $palavrasChave
            );

            if ($stmtPost->execute()) {

                $stmtPost->close();

                header("Location: inicialPage.php?post=criado");
                exit;

            } else {

                error_log("Erro ao criar post: " . $stmtPost->error);

                $erro = "Não foi possível publicar. Tente novamente.";

                $stmtPost->close();
            }
        }

        // Remove a imagem se o registro não tiver sido criado.
        if ($erro !== "" && $caminhoImagem !== null) {
            $arquivoSalvo = __DIR__ . "/../img/posts/"
                . basename($caminhoImagem);

            if (is_file($arquivoSalvo)) {
                unlink($arquivoSalvo);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar publicação - Bem Conecta</title>
    <link rel="stylesheet" href="../css/criarPost.css">
    <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css"
    >
</head>

<body>
<main class="post-container">

    <a href="inicialPage.php" class="back-link">
        ← Voltar ao início
    </a>

    <section class="post-card">

        <h1>Criar publicação</h1>

        <p class="subtitle">
            Compartilhe novidades, ações e projetos com a comunidade.
        </p>

        <?php if ($erro !== ""): ?>
            <div class="error-message">
                <?php
                echo htmlspecialchars(
                    $erro,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </div>
        <?php endif; ?>

        <form
            method="POST"
            action="criarPost.php"
            enctype="multipart/form-data"
        >

            <label for="titulo">Título da publicação</label>

            <input
                type="text"
                id="titulo"
                name="titulo"
                maxlength="70"
                required
                value="<?php echo htmlspecialchars($_POST["titulo"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                placeholder="Ex.: Ajude nossa campanha de materiais escolares"
            >

        
        <label>Imagem da publicação</label>

        <input
            type="file"
            id="imagem"
            name="imagem"
            accept="image/jpeg,image/png,image/webp"
            hidden
        >

        <!-- Tela inicial: enviar imagem -->
        <div id="upload-inicial" class="upload-inicial">
            <button type="button" id="botao-enviar-imagem" class="upload-dropzone">
                <span class="upload-icone">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 16V4m-5 5 5-5 5 5M5 15v4a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-4"/>
                    </svg>
                </span>

                <strong>Escolher uma imagem</strong>
                <span>Clique para selecionar no seu computador</span>
                <small>JPG, PNG ou WEBP · Máximo de 5 MB</small>
            </button>
        </div>

        

        <!-- Tela após confirmar: somente a imagem pronta e dois botões -->
        <div id="imagem-pronta" class="imagem-pronta" hidden>
            <div class="imagem-pronta-quadro">
                <img id="preview-pronto" alt="Imagem pronta para publicação">
                <span class="selo-pronto">Imagem pronta</span>
            </div>

            <div class="acoes-imagem-pronta">
                <button type="button" id="mudar-imagem" class="botao-secundario">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2M16 14l5-5M17 9h4v4M7 15l3-3 3 3"/>
                    </svg>
                    Mudar imagem
                </button>

                <button type="button" id="editar-imagem" class="botao-confirmar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m15 5 4 4M4 20l4-.8L19 8a2.1 2.1 0 0 0-3-3L5 16z"/>
                    </svg>
                    Editar imagem
                </button>
            </div>
        </div>



            <label for="descricao">Descrição da publicação</label>

            <textarea
                id="descricao"
                name="descricao"
                rows="6"
                maxlength="20000"
                required
                placeholder="Descreva a campanha, seu objetivo e como as pessoas podem ajudar..."
            ><?php echo htmlspecialchars($_POST["descricao"] ?? "", ENT_QUOTES, "UTF-8"); ?></textarea>

            <label for="palavrasChave">Palavras-chave (opcional)</label>

            <input
                type="text"
                id="palavrasChave"
                name="palavrasChave"
                maxlength="255"
                value="<?php echo htmlspecialchars($_POST["palavrasChave"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                placeholder="educação, crianças, doação"
            >

            <label for="idCampanha">
                Vincular a uma campanha (opcional)
            </label>

            <select id="idCampanha" name="idCampanha">

                <option value="">Publicação geral da ONG</option>

                <?php foreach ($campanhas as $campanha): ?>
                    <option
                        value="<?php echo (int) $campanha["idCampanha"]; ?>"
                        <?php
                        echo (string)($_POST["idCampanha"] ?? "")
                            === (string)$campanha["idCampanha"]
                            ? "selected"
                            : "";
                        ?>
                    >
                        <?php
                        echo htmlspecialchars(
                            $campanha["titulo"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                        ?>
                    </option>
                <?php endforeach; ?>

            </select>

            <button type="submit" class="publish-btn">
                Publicar
            </button>

        </form>
    </section>
</main>
<!-- Tela de edição -->
    <div id="editor-imagem" class="modal-editor-imagem" hidden>
        <div class="fundo-modal" id="fundo-modal"></div>

        <section
            class="editor-imagem"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-editor"
        >
            <div class="editor-cabecalho">
                <div>
                    <strong id="titulo-editor">Ajuste sua imagem</strong>
                    <span>Arraste para reposicionar e use o zoom para aproximar ou afastar.</span>
                </div>

                <button
                    type="button"
                    id="fechar-editor"
                    class="fechar-editor"
                    aria-label="Fechar editor"
                >×</button>
            </div>

            <div class="area-recorte">
                <img id="imagem-recorte" alt="Ajuste o enquadramento da imagem">
            </div>

            <div class="controles-recorte">
                <button type="button" id="zoom-menos" class="controle-zoom" aria-label="Diminuir zoom">−</button>
                <input type="range" id="controle-zoom" min="0" max="100" value="0" step="1" aria-label="Zoom da imagem">
                <button type="button" id="zoom-mais" class="controle-zoom" aria-label="Aumentar zoom">+</button>
            </div>

            <div class="acoes-editor">
                <button type="button" id="cancelar-recorte" class="botao-secundario">
                    Cancelar
                </button>

                <button type="button" id="confirmar-recorte" class="botao-confirmar">
                    Confirmar imagem
                </button>
            </div>
        </section>
    </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script src="../js/criarPost.js"></script>
</body>
</html>
