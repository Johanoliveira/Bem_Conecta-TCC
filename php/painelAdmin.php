<?php
session_start();
require_once __DIR__ . "/conexao.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

$adminId = (int) $_SESSION["usuario_id"];

// 1. Confirma se o usuário logado realmente possui nivelDeSeguranca = 3
$stmtAuth = $conexao->prepare("
    SELECT idMUsuario, nome, nivelDeSeguranca
    FROM MoldeUsuario
    WHERE idMUsuario = ?
    LIMIT 1
");
$stmtAuth->bind_param("i", $adminId);
$stmtAuth->execute();
$adminDados = $stmtAuth->get_result()->fetch_assoc();
$stmtAuth->close();

if (!$adminDados || (int) $adminDados["nivelDeSeguranca"] !== 3) {
    header("Location: inicialPage.php");
    exit;
}

function normalizarFotoAdmin(?string $caminho): string
{
    $foto = trim((string) $caminho);
    if ($foto === "" || in_array($foto, ["perfil.png", "img/perfil/perfil.png"], true)) {
        return "../img/jpg/ftPerfil.jpg";
    }
    if (str_starts_with($foto, "../") || str_starts_with($foto, "http://") || str_starts_with($foto, "https://")) {
        return $foto;
    }
    return "../" . ltrim($foto, "/");
}

function apagarArquivoImagemPost(?string $caminhoBanco): void
{
    $caminho = trim((string) $caminhoBanco);
    if (str_starts_with($caminho, "../uploadFoto/posts/")) {
        $arquivo = __DIR__ . "/../uploadFoto/posts/" . basename($caminho);
        if (is_file($arquivo)) {
            @unlink($arquivo);
        }
    } elseif (str_starts_with($caminho, "../img/posts/")) {
        $arquivo = __DIR__ . "/../img/posts/" . basename($caminho);
        if (is_file($arquivo)) {
            @unlink($arquivo);
        }
    }
}

// =========================================================
// 2. ENDPOINTS AJAX DE GERENCIAMENTO (POST JSON)
// =========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json; charset=UTF-8");
    $payload = json_decode(file_get_contents("php://input"), true);
    $acao = trim((string) ($payload["acao"] ?? ""));

    // AÇÃO A: ALTERAR NÍVEL DE SEGURANÇA (PROMOVER / REBAIXAR ADMIN)
    if ($acao === "alterar_nivel") {
        $alvoId = (int) ($payload["idMUsuario"] ?? 0);
        $novoNivel = (int) ($payload["novoNivel"] ?? 0);

        if ($alvoId <= 0 || !in_array($novoNivel, [1, 2, 3], true)) {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Parâmetros inválidos."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($alvoId === $adminId) {
            http_response_code(403);
            echo json_encode(["sucesso" => false, "erro" => "Você não pode alterar o nível da sua própria conta."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Verifica se o alvo possui registro na tabela ONGs para não perder o vínculo de nível 2 ao rebaixar
        $stmtCheckOng = $conexao->prepare("SELECT idONG FROM ONGs WHERE idMoldeUsuario = ? LIMIT 1");
        $stmtCheckOng->bind_param("i", $alvoId);
        $stmtCheckOng->execute();
        $ehOng = $stmtCheckOng->get_result()->num_rows > 0;
        $stmtCheckOng->close();

        if ($novoNivel !== 3) {
            $novoNivel = $ehOng ? 2 : 1;
        }

        $stmtUpd = $conexao->prepare("UPDATE MoldeUsuario SET nivelDeSeguranca = ? WHERE idMUsuario = ?");
        $stmtUpd->bind_param("ii", $novoNivel, $alvoId);
        $ok = $stmtUpd->execute();
        $stmtUpd->close();

        echo json_encode([
            "sucesso" => $ok,
            "novoNivel" => $novoNivel,
            "erro" => $ok ? null : "Erro ao atualizar nível de segurança."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // AÇÃO B: EXCLUIR CONTA (USUÁRIO COMUM, ONG OU OUTRO ADMIN)
    if ($acao === "excluir_usuario") {
        $alvoId = (int) ($payload["idMUsuario"] ?? 0);

        if ($alvoId <= 0) {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Conta inválida."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($alvoId === $adminId) {
            http_response_code(403);
            echo json_encode(["sucesso" => false, "erro" => "Você não pode excluir sua própria conta por este painel."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $stmtUser = $conexao->prepare("SELECT idEndereco, fotoPerfil FROM MoldeUsuario WHERE idMUsuario = ? LIMIT 1");
            $stmtUser->bind_param("i", $alvoId);
            $stmtUser->execute();
            $alvoUser = $stmtUser->get_result()->fetch_assoc();
            $stmtUser->close();

            if (!$alvoUser) {
                http_response_code(404);
                echo json_encode(["sucesso" => false, "erro" => "Usuário não encontrado."], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $idEndereco = $alvoUser["idEndereco"] !== null ? (int) $alvoUser["idEndereco"] : null;
            $fotoPerfil = trim((string) ($alvoUser["fotoPerfil"] ?? ""));
            $imagensPostsParaApagar = [];

            $conexao->begin_transaction();

            // 1. Remove curtidas feitas pelo usuário
            $stmt = $conexao->prepare("DELETE FROM curtidas WHERE idMUsuario = ?");
            $stmt->bind_param("i", $alvoId);
            $stmt->execute();
            $stmt->close();

            // 2. Se for UsuarioComum, limpa causas, comentários, favoritos e doações
            $stmtUc = $conexao->prepare("SELECT idUsuarioComum FROM UsuarioComum WHERE idMoldeUsuario = ? LIMIT 1");
            $stmtUc->bind_param("i", $alvoId);
            $stmtUc->execute();
            $uc = $stmtUc->get_result()->fetch_assoc();
            $stmtUc->close();

            if ($uc) {
                $idUc = (int) $uc["idUsuarioComum"];
                foreach (["usuarioCausas", "comentarios", "favoritos", "doacoes"] as $tabela) {
                    $stmtDel = $conexao->prepare("DELETE FROM {$tabela} WHERE idUsuarioComum = ?");
                    $stmtDel->bind_param("i", $idUc);
                    $stmtDel->execute();
                    $stmtDel->close();
                }
                $stmtDelUc = $conexao->prepare("DELETE FROM UsuarioComum WHERE idUsuarioComum = ?");
                $stmtDelUc->bind_param("i", $idUc);
                $stmtDelUc->execute();
                $stmtDelUc->close();
            }

            // 3. Se for ONG, limpa atividades, favoritos, posts, campanhas e doações recebidas
            $stmtOng = $conexao->prepare("SELECT idONG FROM ONGs WHERE idMoldeUsuario = ? LIMIT 1");
            $stmtOng->bind_param("i", $alvoId);
            $stmtOng->execute();
            $ong = $stmtOng->get_result()->fetch_assoc();
            $stmtOng->close();

            if ($ong) {
                $idONG = (int) $ong["idONG"];

                // Coleta imagens dos posts da ONG antes de apagar os registros
                $stmtImgs = $conexao->prepare("
                    SELECT p.conteudo
                    FROM posts p
                    LEFT JOIN campanhas ca ON p.idCampanha = ca.idCampanha
                    WHERE p.idONG = ? OR ca.idONG = ?
                ");
                $stmtImgs->bind_param("ii", $idONG, $idONG);
                $stmtImgs->execute();
                $resImgs = $stmtImgs->get_result();
                while ($imgRow = $resImgs->fetch_assoc()) {
                    $imagensPostsParaApagar[] = $imgRow["conteudo"];
                }
                $stmtImgs->close();

                $stmt = $conexao->prepare("DELETE FROM atividades WHERE idONG = ?");
                $stmt->bind_param("i", $idONG);
                $stmt->execute();
                $stmt->close();

                $stmt = $conexao->prepare("DELETE FROM favoritos WHERE idONG = ?");
                $stmt->bind_param("i", $idONG);
                $stmt->execute();
                $stmt->close();

                $stmt = $conexao->prepare("
                    DELETE c FROM comentarios c
                    INNER JOIN posts p ON c.idPost = p.idPost
                    WHERE p.idONG = ?
                ");
                $stmt->bind_param("i", $idONG);
                $stmt->execute();
                $stmt->close();

                $stmt = $conexao->prepare("
                    DELETE cu FROM curtidas cu
                    INNER JOIN posts p ON cu.idPost = p.idPost
                    WHERE p.idONG = ?
                ");
                $stmt->bind_param("i", $idONG);
                $stmt->execute();
                $stmt->close();

                $stmt = $conexao->prepare("DELETE FROM posts WHERE idONG = ?");
                $stmt->bind_param("i", $idONG);
                $stmt->execute();
                $stmt->close();

                $stmt = $conexao->prepare("
                    DELETE p FROM posts p
                    INNER JOIN campanhas ca ON p.idCampanha = ca.idCampanha
                    WHERE ca.idONG = ?
                ");
                $stmt->bind_param("i", $idONG);
                $stmt->execute();
                $stmt->close();

                $stmt = $conexao->prepare("
                    DELETE cc FROM campanhaCausas cc
                    INNER JOIN campanhas ca ON cc.idCampanha = ca.idCampanha
                    WHERE ca.idONG = ?
                ");
                $stmt->bind_param("i", $idONG);
                $stmt->execute();
                $stmt->close();

                $stmt = $conexao->prepare("
                    DELETE d FROM doacoes d
                    INNER JOIN campanhas ca ON d.idCampanha = ca.idCampanha
                    WHERE ca.idONG = ?
                ");
                $stmt->bind_param("i", $idONG);
                $stmt->execute();
                $stmt->close();

                $stmt = $conexao->prepare("DELETE FROM campanhas WHERE idONG = ?");
                $stmt->bind_param("i", $idONG);
                $stmt->execute();
                $stmt->close();

                $stmt = $conexao->prepare("DELETE FROM ONGs WHERE idONG = ?");
                $stmt->bind_param("i", $idONG);
                $stmt->execute();
                $stmt->close();
            }

            // 4. Exclui o registro base em MoldeUsuario
            $stmtDelBase = $conexao->prepare("DELETE FROM MoldeUsuario WHERE idMUsuario = ?");
            $stmtDelBase->bind_param("i", $alvoId);
            $stmtDelBase->execute();
            $stmtDelBase->close();

            // 5. Remove endereço órfão se existir
            if ($idEndereco !== null && $idEndereco > 0) {
                $stmtEnd = $conexao->prepare("SELECT COUNT(*) AS total FROM MoldeUsuario WHERE idEndereco = ?");
                $stmtEnd->bind_param("i", $idEndereco);
                $stmtEnd->execute();
                $qtdEnd = (int) ($stmtEnd->get_result()->fetch_assoc()["total"] ?? 0);
                $stmtEnd->close();

                if ($qtdEnd === 0) {
                    $stmtDelEnd = $conexao->prepare("DELETE FROM endereco WHERE idEndereco = ?");
                    $stmtDelEnd->bind_param("i", $idEndereco);
                    $stmtDelEnd->execute();
                    $stmtDelEnd->close();
                }
            }

            $conexao->commit();

            // Remove arquivos físicos
            foreach ($imagensPostsParaApagar as $imgPost) {
                apagarArquivoImagemPost($imgPost);
            }

            if ($fotoPerfil !== "" && !str_contains($fotoPerfil, "ftPerfil.jpg") && !str_contains($fotoPerfil, "holder.jpg")) {
                $arquivoFoto = __DIR__ . "/../" . ltrim(str_replace("../", "", $fotoPerfil), "/");
                if (is_file($arquivoFoto)) {
                    @unlink($arquivoFoto);
                }
            }

            echo json_encode(["sucesso" => true], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($conexao->errno) {
                $conexao->rollback();
            }
            error_log("Erro admin ao excluir usuário: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["sucesso" => false, "erro" => "Erro ao excluir a conta no banco."], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // AÇÃO C: EXCLUIR CAMPANHA
    if ($acao === "excluir_campanha") {
        $idCampanha = (int) ($payload["idCampanha"] ?? 0);
        if ($idCampanha <= 0) {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Campanha inválida."], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $imagensPosts = [];
            $stmtImgs = $conexao->prepare("SELECT conteudo FROM posts WHERE idCampanha = ?");
            $stmtImgs->bind_param("i", $idCampanha);
            $stmtImgs->execute();
            $resImgs = $stmtImgs->get_result();
            while ($row = $resImgs->fetch_assoc()) {
                $imagensPosts[] = $row["conteudo"];
            }
            $stmtImgs->close();

            $conexao->begin_transaction();

            $stmt = $conexao->prepare("
                DELETE c FROM comentarios c
                INNER JOIN posts p ON c.idPost = p.idPost
                WHERE p.idCampanha = ?
            ");
            $stmt->bind_param("i", $idCampanha);
            $stmt->execute();
            $stmt->close();

            $stmt = $conexao->prepare("
                DELETE cu FROM curtidas cu
                INNER JOIN posts p ON cu.idPost = p.idPost
                WHERE p.idCampanha = ?
            ");
            $stmt->bind_param("i", $idCampanha);
            $stmt->execute();
            $stmt->close();

            $stmt = $conexao->prepare("DELETE FROM posts WHERE idCampanha = ?");
            $stmt->bind_param("i", $idCampanha);
            $stmt->execute();
            $stmt->close();

            $stmt = $conexao->prepare("DELETE FROM campanhaCausas WHERE idCampanha = ?");
            $stmt->bind_param("i", $idCampanha);
            $stmt->execute();
            $stmt->close();

            $stmt = $conexao->prepare("DELETE FROM doacoes WHERE idCampanha = ?");
            $stmt->bind_param("i", $idCampanha);
            $stmt->execute();
            $stmt->close();

            $stmt = $conexao->prepare("DELETE FROM campanhas WHERE idCampanha = ?");
            $stmt->bind_param("i", $idCampanha);
            $stmt->execute();
            $stmt->close();

            $conexao->commit();

            foreach ($imagensPosts as $img) {
                apagarArquivoImagemPost($img);
            }

            echo json_encode(["sucesso" => true], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($conexao->errno) {
                $conexao->rollback();
            }
            http_response_code(500);
            echo json_encode(["sucesso" => false, "erro" => "Erro ao excluir campanha."], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "Ação desconhecida."], JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================
// 3. CONSULTAS PARA EXIBIÇÃO DO PAINEL
// =========================================================

// Atualiza campanhas expiradas
$conexao->query("
    UPDATE campanhas
    SET status = 'inativa'
    WHERE status = 'ativa'
      AND dataFim IS NOT NULL
      AND dataFim <= NOW()
");

// Totais globais
$totalUsuarios = (int) ($conexao->query("SELECT COUNT(*) AS t FROM MoldeUsuario")->fetch_assoc()["t"] ?? 0);
$totalOngs = (int) ($conexao->query("SELECT COUNT(*) AS t FROM ONGs")->fetch_assoc()["t"] ?? 0);
$totalCampanhas = (int) ($conexao->query("SELECT COUNT(*) AS t FROM campanhas")->fetch_assoc()["t"] ?? 0);
$totalPosts = (int) ($conexao->query("SELECT COUNT(*) AS t FROM posts")->fetch_assoc()["t"] ?? 0);
$totalMovimentado = (float) ($conexao->query("SELECT COALESCE(SUM(valor), 0) AS t FROM doacoes")->fetch_assoc()["t"] ?? 0);
$totalDoacoesQtd = (int) ($conexao->query("SELECT COUNT(*) AS t FROM doacoes")->fetch_assoc()["t"] ?? 0);

// Lista de ONGs cadastradas
$listaOngs = [];
$sqlOngs = "
    SELECT
        o.idONG,
        o.idMoldeUsuario,
        o.descricao,
        o.CNPJ,
        o.Website,
        mu.nome,
        mu.email,
        mu.telefone,
        mu.fotoPerfil,
        mu.dataCriacao,
        mu.nivelDeSeguranca,
        (SELECT COUNT(*) FROM campanhas c WHERE c.idONG = o.idONG) AS qtdCampanhas,
        (SELECT COUNT(*) FROM posts p WHERE p.idONG = o.idONG) AS qtdPosts,
        COALESCE((
            SELECT SUM(d.valor)
            FROM doacoes d
            INNER JOIN campanhas c ON d.idCampanha = c.idCampanha
            WHERE c.idONG = o.idONG
        ), 0) AS totalArrecadado
    FROM ONGs o
    INNER JOIN MoldeUsuario mu ON mu.idMUsuario = o.idMoldeUsuario
    ORDER BY mu.dataCriacao DESC
";
$resOngs = $conexao->query($sqlOngs);
if ($resOngs) {
    while ($row = $resOngs->fetch_assoc()) {
        $row["foto_url"] = normalizarFotoAdmin($row["fotoPerfil"]);
        $listaOngs[] = $row;
    }
}

// Lista de Todos os Usuários
$listaUsuarios = [];
$sqlUsuarios = "
    SELECT
        mu.idMUsuario,
        mu.nome,
        mu.email,
        mu.telefone,
        mu.fotoPerfil,
        mu.dataCriacao,
        mu.nivelDeSeguranca,
        uc.idUsuarioComum,
        uc.CPF,
        o.idONG,
        o.CNPJ
    FROM MoldeUsuario mu
    LEFT JOIN UsuarioComum uc ON uc.idMoldeUsuario = mu.idMUsuario
    LEFT JOIN ONGs o ON o.idMoldeUsuario = mu.idMUsuario
    ORDER BY mu.nivelDeSeguranca DESC, mu.dataCriacao DESC
";
$resUsers = $conexao->query($sqlUsuarios);
if ($resUsers) {
    while ($row = $resUsers->fetch_assoc()) {
        $row["foto_url"] = normalizarFotoAdmin($row["fotoPerfil"]);
        $listaUsuarios[] = $row;
    }
}

// Lista de Campanhas da Plataforma
$listaCampanhas = [];
$sqlCampanhas = "
    SELECT
        c.idCampanha,
        c.titulo,
        c.meta,
        c.dataInicio,
        c.dataFim,
        c.status,
        mu.nome AS nomeONG,
        COALESCE(SUM(d.valor), 0) AS arrecadado,
        COUNT(d.idDoacao) AS qtdDoacoes
    FROM campanhas c
    INNER JOIN ONGs o ON o.idONG = c.idONG
    INNER JOIN MoldeUsuario mu ON mu.idMUsuario = o.idMoldeUsuario
    LEFT JOIN doacoes d ON d.idCampanha = c.idCampanha
    GROUP BY c.idCampanha, c.titulo, c.meta, c.dataInicio, c.dataFim, c.status, mu.nome
    ORDER BY (c.status = 'ativa') DESC, c.dataInicio DESC
";
$resCamp = $conexao->query($sqlCampanhas);
if ($resCamp) {
    while ($row = $resCamp->fetch_assoc()) {
        $meta = (float) ($row["meta"] ?? 0);
        $arrec = (float) $row["arrecadado"];
        $row["meta_float"] = $meta;
        $row["arrecadado_float"] = $arrec;
        $row["percentual"] = $meta > 0 ? min(100, round(($arrec / $meta) * 100)) : 0;
        $listaCampanhas[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Bem Conecta</title>
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/painelAdmin.css?v=1">
</head>
<body>

<?php require_once "header.php"; ?>

<div class="layout-com-sidebar">
    <?php require_once "sidebar.php"; ?>

    <main class="admin-main">

        <!-- BANNER BRANCO TRANSLÚCIDO -->
        <section class="admin-banner">
            <div>
                <span class="admin-selo">🛡️ Nível de Segurança 3 · Controle Geral</span>
                <h1>Painel Administrativo</h1>
                <p>
                    Olá, <strong><?php echo htmlspecialchars($adminDados["nome"]); ?></strong>.
                    Gerencie ONGs parceiras, permissões de usuários e campanhas ativas do Bem Conecta.
                </p>
            </div>

            <div class="admin-banner-acoes">
                <a href="criarCampanha.php" class="btn-admin-verde">+ Nova Campanha</a>
                <a href="criarPost.php" class="btn-admin-outline">+ Nova Publicação</a>
            </div>
        </section>

        <!-- 4 KPI CARDS EM VIDRO LÍQUIDO -->
        <section class="admin-kpi-grid" aria-label="Resumo da Plataforma">
            <article class="kpi-vidro">
                <span class="kpi-label">👥 Contas Cadastradas</span>
                <strong class="kpi-numero"><?php echo $totalUsuarios; ?></strong>
                <small>Usuários comuns, ONGs e admins</small>
            </article>

            <article class="kpi-vidro">
                <span class="kpi-label">🏢 ONGs Parceiras</span>
                <strong class="kpi-numero"><?php echo $totalOngs; ?></strong>
                <small>Organizações registradas no sistema</small>
            </article>

            <article class="kpi-vidro">
                <span class="kpi-label">🎯 Campanhas & Posts</span>
                <strong class="kpi-numero"><?php echo $totalCampanhas; ?> <small>/ <?php echo $totalPosts; ?> posts</small></strong>
                <small>Ações sociais divulgadas</small>
            </article>

            <article class="kpi-vidro">
                <span class="kpi-label">💰 Total Movimentado</span>
                <strong class="kpi-numero destaque">R$ <?php echo number_format($totalMovimentado, 2, ',', '.'); ?></strong>
                <small><?php echo $totalDoacoesQtd; ?> doação(ões) registrada(s)</small>
            </article>
        </section>

        <!-- CONTAINER PRINCIPAL DE GERENCIAMENTO -->
        <section class="admin-painel-card">

            <!-- ABAS DE NAVEGAÇÃO -->
            <div class="admin-tabs" role="tablist">
                <button type="button" class="admin-tab ativo" data-tab="aba-ongs">
                    🏢 Gerenciar ONGs (<span id="contOngsTab"><?php echo count($listaOngs); ?></span>)
                </button>
                <button type="button" class="admin-tab" data-tab="aba-usuarios">
                    👥 Gerenciar Usuários (<span id="contUsuariosTab"><?php echo count($listaUsuarios); ?></span>)
                </button>
                <button type="button" class="admin-tab" data-tab="aba-campanhas">
                    🎯 Gerenciar Campanhas (<span id="contCampanhasTab"><?php echo count($listaCampanhas); ?></span>)
                </button>
            </div>

            <!-- ABA 1: ONGs -->
            <div class="admin-tab-conteudo ativo" id="aba-ongs">
                <div class="admin-filtros">
                    <input
                        type="search"
                        id="buscaOngs"
                        class="admin-input"
                        placeholder="🔎 Pesquisar ONG por nome, CNPJ ou e-mail..."
                    >
                </div>

                <?php if (empty($listaOngs)): ?>
                    <p class="admin-vazio">Nenhuma ONG cadastrada no sistema.</p>
                <?php else: ?>
                    <div class="admin-lista" id="listaOngsAdmin">
                        <?php foreach ($listaOngs as $ong): ?>
                            <?php
                                $buscaOng = mb_strtolower($ong["nome"] . " " . $ong["CNPJ"] . " " . $ong["email"], "UTF-8");
                            ?>
                            <article
                                class="admin-row-card"
                                data-busca="<?php echo htmlspecialchars($buscaOng, ENT_QUOTES, 'UTF-8'); ?>"
                                data-musuario-id="<?php echo (int) $ong["idMoldeUsuario"]; ?>"
                            >
                                <div class="row-identidade">
                                    <img
                                        src="<?php echo htmlspecialchars($ong["foto_url"], ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="Logo ONG"
                                        onerror="this.onerror=null; this.src='../img/jpg/ftPerfil.jpg';"
                                    >
                                    <div class="row-info">
                                        <h3><?php echo htmlspecialchars($ong["nome"]); ?></h3>
                                        <span>📧 <?php echo htmlspecialchars($ong["email"]); ?> · 🪪 CNPJ: <strong><?php echo htmlspecialchars($ong["CNPJ"]); ?></strong></span>
                                        <small><?php echo htmlspecialchars(mb_strimwidth($ong["descricao"], 0, 120, "...")); ?></small>
                                    </div>
                                </div>

                                <div class="row-metricas">
                                    <span class="pill-verde">💰 R$ <?php echo number_format((float) $ong["totalArrecadado"], 2, ',', '.'); ?></span>
                                    <span class="pill-cinza">🎯 <?php echo (int) $ong["qtdCampanhas"]; ?> campanha(s) · 📰 <?php echo (int) $ong["qtdPosts"]; ?> post(s)</span>
                                </div>

                                <div class="row-acoes">
                                    <a href="perfilONG.php?idONG=<?php echo (int) $ong["idONG"]; ?>" class="btn-row-ver">
                                        👁️ Ver Perfil
                                    </a>
                                    <button
                                        type="button"
                                        class="btn-row-excluir btn-excluir-conta-admin"
                                        data-id="<?php echo (int) $ong["idMoldeUsuario"]; ?>"
                                        data-nome="<?php echo htmlspecialchars($ong["nome"], ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        🗑️ Excluir ONG
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ABA 2: USUÁRIOS -->
            <div class="admin-tab-conteudo" id="aba-usuarios">
                <div class="admin-filtros duplo">
                    <input
                        type="search"
                        id="buscaUsuarios"
                        class="admin-input"
                        placeholder="🔎 Pesquisar usuário por nome, e-mail, CPF ou CNPJ..."
                    >

                    <select id="filtroNivelUsuario" class="admin-select">
                        <option value="todos">Todos os perfis</option>
                        <option value="3">🛡️ Administradores (Nível 3)</option>
                        <option value="2">🏢 ONGs (Nível 2)</option>
                        <option value="1">👤 Usuários Comuns (Nível 1)</option>
                    </select>
                </div>

                <div class="admin-lista" id="listaUsuariosAdmin">
                    <?php foreach ($listaUsuarios as $u): ?>
                        <?php
                            $idM = (int) $u["idMUsuario"];
                            $nivelU = (int) $u["nivelDeSeguranca"];
                            $ehProprioAdmin = ($idM === $adminId);
                            $doc = $u["CNPJ"] ? "CNPJ: " . $u["CNPJ"] : ($u["CPF"] ? "CPF: " . $u["CPF"] : "Sem documento");
                            $buscaUser = mb_strtolower($u["nome"] . " " . $u["email"] . " " . $doc, "UTF-8");

                            if ($nivelU === 3) {
                                $badgeTexto = "🛡️ Administrador";
                                $badgeClasse = "badge-admin";
                            } elseif ($nivelU === 2 || $u["idONG"] !== null) {
                                $badgeTexto = "🏢 ONG";
                                $badgeClasse = "badge-ong";
                            } else {
                                $badgeTexto = "👤 Usuário Comum";
                                $badgeClasse = "badge-comum";
                            }
                        ?>
                        <article
                            class="admin-row-card"
                            data-busca="<?php echo htmlspecialchars($buscaUser, ENT_QUOTES, 'UTF-8'); ?>"
                            data-nivel="<?php echo $nivelU; ?>"
                            data-musuario-id="<?php echo $idM; ?>"
                        >
                            <div class="row-identidade">
                                <img
                                    src="<?php echo htmlspecialchars($u["foto_url"], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="Foto usuário"
                                    onerror="this.onerror=null; this.src='../img/jpg/ftPerfil.jpg';"
                                >
                                <div class="row-info">
                                    <div class="row-titulo-linha">
                                        <h3><?php echo htmlspecialchars($u["nome"]); ?></h3>
                                        <span class="badge-nivel <?php echo $badgeClasse; ?>"><?php echo $badgeTexto; ?></span>
                                        <?php if ($ehProprioAdmin): ?>
                                            <span class="badge-voce">Você</span>
                                        <?php endif; ?>
                                    </div>
                                    <span>📧 <?php echo htmlspecialchars($u["email"]); ?> · 🪪 <?php echo htmlspecialchars($doc); ?></span>
                                    <small>📅 Cadastro em <?php echo date("d/m/Y", strtotime($u["dataCriacao"])); ?> · 📞 <?php echo htmlspecialchars($u["telefone"] ?: "-"); ?></small>
                                </div>
                            </div>

                            <div class="row-acoes">
                                <?php if (!$ehProprioAdmin): ?>
                                    <?php if ($nivelU === 3): ?>
                                        <button
                                            type="button"
                                            class="btn-row-nivel btn-alterar-nivel"
                                            data-id="<?php echo $idM; ?>"
                                            data-novo-nivel="1"
                                            data-nome="<?php echo htmlspecialchars($u["nome"], ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            ⬇️ Remover Admin
                                        </button>
                                    <?php else: ?>
                                        <button
                                            type="button"
                                            class="btn-row-promover btn-alterar-nivel"
                                            data-id="<?php echo $idM; ?>"
                                            data-novo-nivel="3"
                                            data-nome="<?php echo htmlspecialchars($u["nome"], ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            ⬆️ Tornar Admin
                                        </button>
                                    <?php endif; ?>

                                    <button
                                        type="button"
                                        class="btn-row-excluir btn-excluir-conta-admin"
                                        data-id="<?php echo $idM; ?>"
                                        data-nome="<?php echo htmlspecialchars($u["nome"], ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        🗑️ Excluir
                                    </button>
                                <?php else: ?>
                                    <span class="texto-protegido">Conta atual protegida</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ABA 3: CAMPANHAS -->
            <div class="admin-tab-conteudo" id="aba-campanhas">
                <div class="admin-filtros">
                    <input
                        type="search"
                        id="buscaCampanhas"
                        class="admin-input"
                        placeholder="🔎 Pesquisar campanha por título ou ONG..."
                    >
                </div>

                <?php if (empty($listaCampanhas)): ?>
                    <p class="admin-vazio">Nenhuma campanha cadastrada.</p>
                <?php else: ?>
                    <div class="admin-lista" id="listaCampanhasAdmin">
                        <?php foreach ($listaCampanhas as $camp): ?>
                            <?php
                                $ehAtiva = strtolower((string) $camp["status"]) === "ativa";
                                $buscaCamp = mb_strtolower($camp["titulo"] . " " . $camp["nomeONG"], "UTF-8");
                            ?>
                            <article
                                class="admin-row-card"
                                data-busca="<?php echo htmlspecialchars($buscaCamp, ENT_QUOTES, 'UTF-8'); ?>"
                                data-campanha-id="<?php echo (int) $camp["idCampanha"]; ?>"
                            >
                                <div class="row-identidade">
                                    <div class="row-info">
                                        <div class="row-titulo-linha">
                                            <h3><?php echo htmlspecialchars($camp["titulo"]); ?></h3>
                                            <span class="badge-status-camp <?php echo $ehAtiva ? 'ativa' : 'inativa'; ?>">
                                                <?php echo $ehAtiva ? '● Ativa' : '○ Inativa'; ?>
                                            </span>
                                        </div>
                                        <span>🏢 ONG: <strong><?php echo htmlspecialchars($camp["nomeONG"]); ?></strong></span>
                                        <small>
                                            💰 Arrecadado: <strong>R$ <?php echo number_format($camp["arrecadado_float"], 2, ',', '.'); ?></strong>
                                            <?php if ($camp["meta_float"] > 0): ?>
                                                de R$ <?php echo number_format($camp["meta_float"], 2, ',', '.'); ?> (<?php echo (int) $camp["percentual"]; ?>%)
                                            <?php endif; ?>
                                            · <?php echo (int) $camp["qtdDoacoes"]; ?> doação(ões)
                                        </small>
                                    </div>
                                </div>

                                <div class="row-acoes">
                                    <button
                                        type="button"
                                        class="btn-row-excluir btn-excluir-campanha-admin"
                                        data-id="<?php echo (int) $camp["idCampanha"]; ?>"
                                        data-titulo="<?php echo htmlspecialchars($camp["titulo"], ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        🗑️ Excluir Campanha
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </section>
    </main>
</div>

<script>
(function () {
    // Alternância de Abas
    const tabs = document.querySelectorAll(".admin-tab");
    const conteudos = document.querySelectorAll(".admin-tab-conteudo");

    tabs.forEach((tab) => {
        tab.addEventListener("click", () => {
            tabs.forEach((t) => t.classList.remove("ativo"));
            conteudos.forEach((c) => c.classList.remove("ativo"));
            tab.classList.add("ativo");
            document.getElementById(tab.dataset.tab)?.classList.add("ativo");
        });
    });

    // Filtro de ONGs
    document.getElementById("buscaOngs")?.addEventListener("input", function () {
        const termo = this.value.trim().toLowerCase();
        document.querySelectorAll("#listaOngsAdmin .admin-row-card").forEach((card) => {
            card.style.display = (card.dataset.busca || "").includes(termo) ? "flex" : "none";
        });
    });

    // Filtro de Usuários
    const buscaUsuarios = document.getElementById("buscaUsuarios");
    const filtroNivel = document.getElementById("filtroNivelUsuario");

    function filtrarUsuarios() {
        const termo = (buscaUsuarios?.value || "").trim().toLowerCase();
        const nivel = filtroNivel?.value || "todos";

        document.querySelectorAll("#listaUsuariosAdmin .admin-row-card").forEach((card) => {
            const matchTexto = (card.dataset.busca || "").includes(termo);
            const matchNivel = nivel === "todos" || card.dataset.nivel === nivel;
            card.style.display = (matchTexto && matchNivel) ? "flex" : "none";
        });
    }

    buscaUsuarios?.addEventListener("input", filtrarUsuarios);
    filtroNivel?.addEventListener("change", filtrarUsuarios);

    // Filtro de Campanhas
    document.getElementById("buscaCampanhas")?.addEventListener("input", function () {
        const termo = this.value.trim().toLowerCase();
        document.querySelectorAll("#listaCampanhasAdmin .admin-row-card").forEach((card) => {
            card.style.display = (card.dataset.busca || "").includes(termo) ? "flex" : "none";
        });
    });

    // Promover / Rebaixar Nível Admin
    document.addEventListener("click", async (e) => {
        const btn = e.target.closest(".btn-alterar-nivel");
        if (!btn || btn.disabled) return;

        const idMUsuario = Number(btn.dataset.id);
        const novoNivel = Number(btn.dataset.novoNivel);
        const nome = btn.dataset.nome || "este usuário";
        const acaoTexto = novoNivel === 3
            ? `promover "${nome}" a Administrador (Nível 3)`
            : `remover os privilégios de Administrador de "${nome}"`;

        if (!confirm(`Deseja realmente ${acaoTexto}?`)) return;

        btn.disabled = true;
        try {
            const resp = await fetch("painelAdmin.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ acao: "alterar_nivel", idMUsuario, novoNivel })
            });
            const dados = await resp.json();
            if (!resp.ok || !dados.sucesso) {
                alert(dados.erro || "Não foi possível alterar o nível.");
                btn.disabled = false;
                return;
            }
            window.location.reload();
        } catch (err) {
            alert("Erro de conexão ao atualizar permissão.");
            btn.disabled = false;
        }
    });

    // Excluir Conta (ONG ou Usuário)
    document.addEventListener("click", async (e) => {
        const btn = e.target.closest(".btn-excluir-conta-admin");
        if (!btn || btn.disabled) return;

        const idMUsuario = Number(btn.dataset.id);
        const nome = btn.dataset.nome || "esta conta";

        if (!confirm(`ATENÇÃO: Deseja excluir permanentemente "${nome}" e todos os registros vinculados?`)) {
            return;
        }

        btn.disabled = true;
        btn.textContent = "Excluindo...";

        try {
            const resp = await fetch("painelAdmin.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ acao: "excluir_usuario", idMUsuario })
            });
            const dados = await resp.json();

            if (!resp.ok || !dados.sucesso) {
                alert(dados.erro || "Erro ao excluir a conta.");
                btn.disabled = false;
                btn.textContent = "🗑️ Excluir";
                return;
            }

            document.querySelectorAll(`[data-musuario-id="${idMUsuario}"]`).forEach((el) => el.remove());
        } catch (err) {
            alert("Erro de conexão ao excluir conta.");
            btn.disabled = false;
        }
    });

    // Excluir Campanha
    document.addEventListener("click", async (e) => {
        const btn = e.target.closest(".btn-excluir-campanha-admin");
        if (!btn || btn.disabled) return;

        const idCampanha = Number(btn.dataset.id);
        const titulo = btn.dataset.titulo || "esta campanha";

        if (!confirm(`Deseja excluir a campanha "${titulo}" e seus posts vinculados?`)) {
            return;
        }

        btn.disabled = true;
        btn.textContent = "Excluindo...";

        try {
            const resp = await fetch("painelAdmin.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ acao: "excluir_campanha", idCampanha })
            });
            const dados = await resp.json();

            if (!resp.ok || !dados.sucesso) {
                alert(dados.erro || "Erro ao excluir campanha.");
                btn.disabled = false;
                btn.textContent = "🗑️ Excluir Campanha";
                return;
            }

            document.querySelector(`[data-campanha-id="${idCampanha}"]`)?.remove();
        } catch (err) {
            alert("Erro de conexão ao excluir campanha.");
            btn.disabled = false;
        }
    });
})();
</script>
</body>
</html>