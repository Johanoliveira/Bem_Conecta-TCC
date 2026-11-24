<?php
session_start();
require_once __DIR__ . "/conexao.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

$adminId = (int) $_SESSION["usuario_id"];

// Garante que apenas contas com nivelDeSeguranca = 3 acessem
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
// AÇÕES ADMINISTRATIVAS (MODERAR / ALTERAR PERMISSÃO)
// =========================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json; charset=UTF-8");
    $payload = json_decode(file_get_contents("php://input"), true);
    $acao = trim((string) ($payload["acao"] ?? ""));

    if ($acao === "alterar_nivel") {
        $alvoId = (int) ($payload["idMUsuario"] ?? 0);
        $novoNivel = (int) ($payload["novoNivel"] ?? 0);

        if ($alvoId <= 0 || !in_array($novoNivel, [1, 2, 3], true) || $alvoId === $adminId) {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Operação não permitida."], JSON_UNESCAPED_UNICODE);
            exit;
        }

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

        echo json_encode(["sucesso" => $ok, "novoNivel" => $novoNivel], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($acao === "excluir_usuario") {
        $alvoId = (int) ($payload["idMUsuario"] ?? 0);
        if ($alvoId <= 0 || $alvoId === $adminId) {
            http_response_code(400);
            echo json_encode(["sucesso" => false, "erro" => "Conta inválida ou protegida."], JSON_UNESCAPED_UNICODE);
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
            $imagensPosts = [];

            $conexao->begin_transaction();

            $stmt = $conexao->prepare("DELETE FROM curtidas WHERE idMUsuario = ?");
            $stmt->bind_param("i", $alvoId);
            $stmt->execute();
            $stmt->close();

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

            $stmtOng = $conexao->prepare("SELECT idONG FROM ONGs WHERE idMoldeUsuario = ? LIMIT 1");
            $stmtOng->bind_param("i", $alvoId);
            $stmtOng->execute();
            $ong = $stmtOng->get_result()->fetch_assoc();
            $stmtOng->close();

            if ($ong) {
                $idONG = (int) $ong["idONG"];

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
                    $imagensPosts[] = $imgRow["conteudo"];
                }
                $stmtImgs->close();

                foreach ([
                    "DELETE FROM atividades WHERE idONG = ?",
                    "DELETE FROM favoritos WHERE idONG = ?",
                    "DELETE c FROM comentarios c INNER JOIN posts p ON c.idPost = p.idPost WHERE p.idONG = ?",
                    "DELETE cu FROM curtidas cu INNER JOIN posts p ON cu.idPost = p.idPost WHERE p.idONG = ?",
                    "DELETE FROM posts WHERE idONG = ?",
                    "DELETE p FROM posts p INNER JOIN campanhas ca ON p.idCampanha = ca.idCampanha WHERE ca.idONG = ?",
                    "DELETE cc FROM campanhaCausas cc INNER JOIN campanhas ca ON cc.idCampanha = ca.idCampanha WHERE ca.idONG = ?",
                    "DELETE d FROM doacoes d INNER JOIN campanhas ca ON d.idCampanha = ca.idCampanha WHERE ca.idONG = ?",
                    "DELETE FROM campanhas WHERE idONG = ?",
                    "DELETE FROM ONGs WHERE idONG = ?"
                ] as $queryOng) {
                    $stmtTmp = $conexao->prepare($queryOng);
                    $stmtTmp->bind_param("i", $idONG);
                    $stmtTmp->execute();
                    $stmtTmp->close();
                }
            }

            $stmtDelBase = $conexao->prepare("DELETE FROM MoldeUsuario WHERE idMUsuario = ?");
            $stmtDelBase->bind_param("i", $alvoId);
            $stmtDelBase->execute();
            $stmtDelBase->close();

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

            foreach ($imagensPosts as $img) {
                apagarArquivoImagemPost($img);
            }
            if ($fotoPerfil !== "" && !str_contains($fotoPerfil, "ftPerfil.jpg") && !str_contains($fotoPerfil, "holder.jpg")) {
                $arqFoto = __DIR__ . "/../" . ltrim(str_replace("../", "", $fotoPerfil), "/");
                if (is_file($arqFoto)) {
                    @unlink($arqFoto);
                }
            }

            echo json_encode(["sucesso" => true], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if ($conexao->errno) {
                $conexao->rollback();
            }
            http_response_code(500);
            echo json_encode(["sucesso" => false, "erro" => "Erro ao remover conta."], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

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

            foreach ([
                "DELETE c FROM comentarios c INNER JOIN posts p ON c.idPost = p.idPost WHERE p.idCampanha = ?",
                "DELETE cu FROM curtidas cu INNER JOIN posts p ON cu.idPost = p.idPost WHERE p.idCampanha = ?",
                "DELETE FROM posts WHERE idCampanha = ?",
                "DELETE FROM campanhaCausas WHERE idCampanha = ?",
                "DELETE FROM doacoes WHERE idCampanha = ?",
                "DELETE FROM campanhas WHERE idCampanha = ?"
            ] as $sqlDelCamp) {
                $stmtC = $conexao->prepare($sqlDelCamp);
                $stmtC->bind_param("i", $idCampanha);
                $stmtC->execute();
                $stmtC->close();
            }

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
            echo json_encode(["sucesso" => false, "erro" => "Erro ao remover campanha."], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "Ação inválida."], JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================================================
// MÉTRICAS E INDICADORES GLOBAIS DA PLATAFORMA
// =========================================================
$conexao->query("
    UPDATE campanhas
    SET status = 'inativa'
    WHERE status = 'ativa'
      AND dataFim IS NOT NULL
      AND dataFim <= NOW()
");

// 1. Métricas financeiras
$fin = $conexao->query("
    SELECT
        COALESCE(SUM(valor), 0) AS totalArrecadado,
        COUNT(*) AS totalDoacoes,
        COUNT(DISTINCT idUsuarioComum) AS doadoresAtivos,
        COALESCE(AVG(valor), 0) AS ticketMedio,
        COALESCE(MAX(valor), 0) AS maiorDoacao
    FROM doacoes
")->fetch_assoc();

$totalArrecadado = (float) ($fin["totalArrecadado"] ?? 0);
$totalDoacoes = (int) ($fin["totalDoacoes"] ?? 0);
$doadoresAtivos = (int) ($fin["doadoresAtivos"] ?? 0);
$ticketMedio = (float) ($fin["ticketMedio"] ?? 0);
$maiorDoacao = (float) ($fin["maiorDoacao"] ?? 0);

// 2. Métricas de contas
$totalUsuariosGeral = (int) ($conexao->query("SELECT COUNT(*) AS t FROM MoldeUsuario")->fetch_assoc()["t"] ?? 0);
$totalOngs = (int) ($conexao->query("SELECT COUNT(*) AS t FROM ONGs")->fetch_assoc()["t"] ?? 0);
$totalAdmins = (int) ($conexao->query("SELECT COUNT(*) AS t FROM MoldeUsuario WHERE nivelDeSeguranca = 3")->fetch_assoc()["t"] ?? 0);
$totalDoadoresCadastrados = max(0, $totalUsuariosGeral - $totalOngs - $totalAdmins);

$taxaConversaoDoadores = $totalDoadoresCadastrados > 0
    ? round(($doadoresAtivos / $totalDoadoresCadastrados) * 100)
    : 0;

// 3. Métricas de campanhas e metas
$campStats = $conexao->query("
    SELECT
        COUNT(*) AS totalCampanhas,
        SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) AS ativas,
        COALESCE(SUM(meta), 0) AS somaMetas
    FROM campanhas
")->fetch_assoc();

$totalCampanhas = (int) ($campStats["totalCampanhas"] ?? 0);
$campanhasAtivas = (int) ($campStats["ativas"] ?? 0);
$somaMetas = (float) ($campStats["somaMetas"] ?? 0);
$atingimentoGlobalMetas = $somaMetas > 0 ? min(100, round(($totalArrecadado / $somaMetas) * 100)) : 0;

// 4. Métricas de engajamento social
$totalPosts = (int) ($conexao->query("SELECT COUNT(*) AS t FROM posts")->fetch_assoc()["t"] ?? 0);
$totalCurtidas = (int) ($conexao->query("SELECT COUNT(*) AS t FROM curtidas")->fetch_assoc()["t"] ?? 0);
$totalComentarios = (int) ($conexao->query("SELECT COUNT(*) AS t FROM comentarios")->fetch_assoc()["t"] ?? 0);
$totalFavoritos = (int) ($conexao->query("SELECT COUNT(*) AS t FROM favoritos")->fetch_assoc()["t"] ?? 0);

// 5. Gráfico: Arrecadação mensal (últimos 6 meses)
$arrecadacaoMensal = [];
$maxMensal = 0.0;
$resMensal = $conexao->query("
    SELECT
        DATE_FORMAT(dataDoacao, '%Y-%m') AS mesAno,
        DATE_FORMAT(dataDoacao, '%m/%Y') AS labelMes,
        COALESCE(SUM(valor), 0) AS totalMes,
        COUNT(*) AS qtdMes
    FROM doacoes
    GROUP BY mesAno, labelMes
    ORDER BY mesAno DESC
    LIMIT 6
");
if ($resMensal) {
    while ($row = $resMensal->fetch_assoc()) {
        $val = (float) $row["totalMes"];
        if ($val > $maxMensal) {
            $maxMensal = $val;
        }
        $arrecadacaoMensal[] = $row;
    }
    $arrecadacaoMensal = array_reverse($arrecadacaoMensal);
}

// 6. Ranking de ONGs por Arrecadação e Engajamento
$rankingOngs = [];
$maxOngArrecadado = 0.0;
$resOngs = $conexao->query("
    SELECT
        o.idONG,
        o.idMoldeUsuario,
        o.CNPJ,
        mu.nome,
        mu.email,
        mu.fotoPerfil,
        (SELECT COUNT(*) FROM campanhas c WHERE c.idONG = o.idONG) AS qtdCampanhas,
        (SELECT COUNT(*) FROM posts p WHERE p.idONG = o.idONG) AS qtdPosts,
        (SELECT COUNT(*) FROM favoritos f WHERE f.idONG = o.idONG) AS qtdFavoritos,
        COALESCE((
            SELECT SUM(d.valor)
            FROM doacoes d
            INNER JOIN campanhas c ON d.idCampanha = c.idCampanha
            WHERE c.idONG = o.idONG
        ), 0) AS totalArrecadado
    FROM ONGs o
    INNER JOIN MoldeUsuario mu ON mu.idMUsuario = o.idMoldeUsuario
    ORDER BY totalArrecadado DESC, mu.nome ASC
");
if ($resOngs) {
    while ($row = $resOngs->fetch_assoc()) {
        $row["foto_url"] = normalizarFotoAdmin($row["fotoPerfil"]);
        $val = (float) $row["totalArrecadado"];
        if ($val > $maxOngArrecadado) {
            $maxOngArrecadado = $val;
        }
        $rankingOngs[] = $row;
    }
}

// 7. Auditoria: Últimas 10 doações da plataforma
$ultimasDoacoes = [];
$resUltimas = $conexao->query("
    SELECT
        d.idDoacao,
        d.valor,
        d.dataDoacao,
        muDoador.nome AS nomeDoador,
        muOng.nome AS nomeONG,
        c.titulo AS tituloCampanha
    FROM doacoes d
    INNER JOIN UsuarioComum uc ON uc.idUsuarioComum = d.idUsuarioComum
    INNER JOIN MoldeUsuario muDoador ON muDoador.idMUsuario = uc.idMoldeUsuario
    INNER JOIN campanhas c ON c.idCampanha = d.idCampanha
    INNER JOIN ONGs o ON o.idONG = c.idONG
    INNER JOIN MoldeUsuario muOng ON muOng.idMUsuario = o.idMoldeUsuario
    ORDER BY d.dataDoacao DESC, d.idDoacao DESC
    LIMIT 10
");
if ($resUltimas) {
    while ($row = $resUltimas->fetch_assoc()) {
        $ultimasDoacoes[] = $row;
    }
}

// 8. Listas para Moderação (Contas e Campanhas)
$listaUsuarios = [];
$resUsers = $conexao->query("
    SELECT
        mu.idMUsuario,
        mu.nome,
        mu.email,
        mu.fotoPerfil,
        mu.dataCriacao,
        mu.nivelDeSeguranca,
        o.idONG
    FROM MoldeUsuario mu
    LEFT JOIN ONGs o ON o.idMoldeUsuario = mu.idMUsuario
    ORDER BY mu.nivelDeSeguranca DESC, mu.dataCriacao DESC
");
if ($resUsers) {
    while ($row = $resUsers->fetch_assoc()) {
        $row["foto_url"] = normalizarFotoAdmin($row["fotoPerfil"]);
        $listaUsuarios[] = $row;
    }
}

$listaCampanhas = [];
$resCamp = $conexao->query("
    SELECT
        c.idCampanha,
        c.titulo,
        c.meta,
        c.status,
        mu.nome AS nomeONG,
        COALESCE(SUM(d.valor), 0) AS arrecadado
    FROM campanhas c
    INNER JOIN ONGs o ON o.idONG = c.idONG
    INNER JOIN MoldeUsuario mu ON mu.idMUsuario = o.idMoldeUsuario
    LEFT JOIN doacoes d ON d.idCampanha = c.idCampanha
    GROUP BY c.idCampanha, c.titulo, c.meta, c.status, mu.nome
    ORDER BY (c.status = 'ativa') DESC, c.dataInicio DESC
");
if ($resCamp) {
    while ($row = $resCamp->fetch_assoc()) {
        $listaCampanhas[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Métricas do Sistema - Bem Conecta</title>
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/painelAdmin.css?v=2">
</head>
<body>

<?php require_once "header.php"; ?>

<div class="layout-com-sidebar">
    <?php require_once "sidebar.php"; ?>

    <main class="admin-main">

        <!-- CABEÇALHO DO PAINEL DE MÉTRICAS -->
        <section class="admin-banner">
            <div>
                <span class="admin-selo">📊 Central de Métricas & Auditoria</span>
                <h1>Indicadores da Plataforma</h1>
                <p>
                    Monitoramento em tempo real de arrecadações, crescimento da base de usuários, engajamento social e integridade do <strong>Bem Conecta</strong>.
                </p>
            </div>

            <div class="admin-banner-acoes">
                <a href="inicialPage.php" class="btn-admin-outline">📰 Auditar Feed</a>
                <a href="ongs.php" class="btn-admin-outline">🏢 Ver ONGs</a>
            </div>
        </section>

        <!-- 4 KPI CARDS EM VIDRO LÍQUIDO -->
        <section class="admin-kpi-grid" aria-label="Indicadores Principais">
            <article class="kpi-vidro">
                <span class="kpi-label">💰 Volume Total Movimentado</span>
                <strong class="kpi-numero destaque">R$ <?php echo number_format($totalArrecadado, 2, ',', '.'); ?></strong>
                <small><?php echo $totalDoacoes; ?> doações · Ticket médio de R$ <?php echo number_format($ticketMedio, 2, ',', '.'); ?></small>
            </article>

            <article class="kpi-vidro">
                <span class="kpi-label">👥 Ecossistema de Contas</span>
                <strong class="kpi-numero"><?php echo $totalUsuariosGeral; ?></strong>
                <small><?php echo $totalDoadoresCadastrados; ?> doador(es) · <?php echo $totalOngs; ?> ONG(s) · <?php echo $totalAdmins; ?> admin(s)</small>
            </article>

            <article class="kpi-vidro">
                <span class="kpi-label">🎯 Desempenho de Campanhas</span>
                <strong class="kpi-numero"><?php echo $campanhasAtivas; ?> <small>ativas / <?php echo $totalCampanhas; ?></small></strong>
                <small><?php echo $atingimentoGlobalMetas; ?>% da meta global atingida</small>
            </article>

            <article class="kpi-vidro">
                <span class="kpi-label">💬 Engajamento da Rede</span>
                <strong class="kpi-numero"><?php echo ($totalCurtidas + $totalComentarios + $totalFavoritos); ?></strong>
                <small>❤️ <?php echo $totalCurtidas; ?> curtidas · 💬 <?php echo $totalComentarios; ?> coment. · 📌 <?php echo $totalFavoritos; ?> fav.</small>
            </article>
        </section>

        <!-- BLOCO DE GRÁFICOS ANALÍTICOS -->
        <section class="metricas-dupla">

            <!-- GRÁFICO 1: EVOLUÇÃO MENSAL DE DOAÇÕES -->
            <article class="card-branco-vidro">
                <div class="card-titulo-bloco">
                    <h2>📈 Arrecadação Mensal (Últimos 6 Meses)</h2>
                    <p>Evolução financeira das doações confirmadas na plataforma.</p>
                </div>

                <?php if (empty($arrecadacaoMensal)): ?>
                    <p class="admin-vazio">Ainda não há histórico mensal de doações para exibir o gráfico.</p>
                <?php else: ?>
                    <div class="grafico-colunas">
                        <?php foreach ($arrecadacaoMensal as $mes): ?>
                            <?php
                                $valorMes = (float) $mes["totalMes"];
                                $alturaPct = $maxMensal > 0 ? max(12, round(($valorMes / $maxMensal) * 100)) : 12;
                            ?>
                            <div class="coluna-item">
                                <span class="coluna-valor">R$ <?php echo number_format($valorMes, 0, ',', '.'); ?></span>
                                <div class="coluna-trilho">
                                    <div class="coluna-barra" style="height: <?php echo $alturaPct; ?>%;" title="<?php echo (int) $mes['qtdMes']; ?> doação(ões)"></div>
                                </div>
                                <span class="coluna-label"><?php echo htmlspecialchars($mes["labelMes"]); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="mini-metricas-rodape">
                    <div>
                        <span>Taxa de Doadores Ativos</span>
                        <strong><?php echo $taxaConversaoDoadores; ?>%</strong>
                    </div>
                    <div>
                        <span>Maior Doação Única</span>
                        <strong>R$ <?php echo number_format($maiorDoacao, 2, ',', '.'); ?></strong>
                    </div>
                    <div>
                        <span>Publicações Totais</span>
                        <strong><?php echo $totalPosts; ?> post(s)</strong>
                    </div>
                </div>
            </article>

            <!-- GRÁFICO 2: RANKING DE ARRECADAÇÃO POR ONG -->
            <article class="card-branco-vidro">
                <div class="card-titulo-bloco">
                    <h2>🏆 Participação por ONG</h2>
                    <p>Comparativo de recursos captados e seguidores favoritos por instituição.</p>
                </div>

                <?php if (empty($rankingOngs)): ?>
                    <p class="admin-vazio">Nenhuma ONG cadastrada ainda.</p>
                <?php else: ?>
                    <div class="ranking-barras-lista">
                        <?php foreach ($rankingOngs as $ong): ?>
                            <?php
                                $arrecOng = (float) $ong["totalArrecadado"];
                                $larguraPct = $maxOngArrecadado > 0 ? round(($arrecOng / $maxOngArrecadado) * 100) : 0;
                            ?>
                            <div class="ranking-barra-item">
                                <div class="ranking-barra-topo">
                                    <div class="ranking-ong-nome">
                                        <img
                                            src="<?php echo htmlspecialchars($ong["foto_url"], ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="Logo"
                                            onerror="this.onerror=null; this.src='../img/jpg/ftPerfil.jpg';"
                                        >
                                        <strong><?php echo htmlspecialchars($ong["nome"]); ?></strong>
                                    </div>
                                    <span class="ranking-ong-valor">
                                        R$ <?php echo number_format($arrecOng, 2, ',', '.'); ?>
                                        <small>(📌 <?php echo (int) $ong["qtdFavoritos"]; ?>)</small>
                                    </span>
                                </div>
                                <div class="barra-horizontal-trilho">
                                    <div class="barra-horizontal-fill" style="width: <?php echo $larguraPct; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

        </section>

        <!-- AUDITORIA + MODERAÇÃO ENXUTA -->
        <section class="admin-painel-card">
            <div class="admin-tabs" role="tablist">
                <button type="button" class="admin-tab ativo" data-tab="aba-auditoria">
                    🧾 Auditoria de Doações (<?php echo count($ultimasDoacoes); ?>)
                </button>
                <button type="button" class="admin-tab" data-tab="aba-usuarios">
                    👥 Controle de Contas (<?php echo count($listaUsuarios); ?>)
                </button>
                <button type="button" class="admin-tab" data-tab="aba-campanhas">
                    🎯 Moderação de Campanhas (<?php echo count($listaCampanhas); ?>)
                </button>
            </div>

            <!-- ABA 1: AUDITORIA DE TRANSAÇÕES -->
            <div class="admin-tab-conteudo ativo" id="aba-auditoria">
                <?php if (empty($ultimasDoacoes)): ?>
                    <p class="admin-vazio">Nenhuma transação registrada na plataforma até o momento.</p>
                <?php else: ?>
                    <div class="admin-lista">
                        <?php foreach ($ultimasDoacoes as $d): ?>
                            <article class="admin-row-card">
                                <div class="row-info">
                                    <h3>
                                        <?php echo htmlspecialchars($d["nomeDoador"]); ?>
                                        <span style="font-weight: 400; color: #555;">apoiou</span>
                                        <?php echo htmlspecialchars($d["nomeONG"]); ?>
                                    </h3>
                                    <span>🎯 Campanha: <strong><?php echo htmlspecialchars($d["tituloCampanha"]); ?></strong></span>
                                    <small>🕒 <?php echo date("d/m/Y \à\s H:i", strtotime($d["dataDoacao"])); ?> · Registro #<?php echo (int) $d["idDoacao"]; ?></small>
                                </div>
                                <span class="pill-verde">
                                    + R$ <?php echo number_format((float) $d["valor"], 2, ',', '.'); ?>
                                </span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ABA 2: CONTROLE DE CONTAS -->
            <div class="admin-tab-conteudo" id="aba-usuarios">
                <div class="admin-filtros duplo">
                    <input type="search" id="buscaUsuarios" class="admin-input" placeholder="🔎 Filtrar por nome ou e-mail...">
                    <select id="filtroNivelUsuario" class="admin-select">
                        <option value="todos">Todos os perfis</option>
                        <option value="3">🛡️ Administradores</option>
                        <option value="2">🏢 ONGs</option>
                        <option value="1">👤 Doadores</option>
                    </select>
                </div>

                <div class="admin-lista" id="listaUsuariosAdmin">
                    <?php foreach ($listaUsuarios as $u): ?>
                        <?php
                            $idM = (int) $u["idMUsuario"];
                            $nivelU = (int) $u["nivelDeSeguranca"];
                            $ehProprio = ($idM === $adminId);
                            $buscaUser = mb_strtolower($u["nome"] . " " . $u["email"], "UTF-8");

                            if ($nivelU === 3) {
                                $badgeTexto = "🛡️ Admin";
                                $badgeClasse = "badge-admin";
                            } elseif ($nivelU === 2 || $u["idONG"] !== null) {
                                $badgeTexto = "🏢 ONG";
                                $badgeClasse = "badge-ong";
                            } else {
                                $badgeTexto = "👤 Doador";
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
                                    alt="Avatar"
                                    onerror="this.onerror=null; this.src='../img/jpg/ftPerfil.jpg';"
                                >
                                <div class="row-info">
                                    <div class="row-titulo-linha">
                                        <h3><?php echo htmlspecialchars($u["nome"]); ?></h3>
                                        <span class="badge-nivel <?php echo $badgeClasse; ?>"><?php echo $badgeTexto; ?></span>
                                    </div>
                                    <span>📧 <?php echo htmlspecialchars($u["email"]); ?> · Cadastro em <?php echo date("d/m/Y", strtotime($u["dataCriacao"])); ?></span>
                                </div>
                            </div>

                            <div class="row-acoes">
                                <?php if (!$ehProprio): ?>
                                    <button
                                        type="button"
                                        class="<?php echo $nivelU === 3 ? 'btn-row-nivel' : 'btn-row-promover'; ?> btn-alterar-nivel"
                                        data-id="<?php echo $idM; ?>"
                                        data-novo-nivel="<?php echo $nivelU === 3 ? 1 : 3; ?>"
                                        data-nome="<?php echo htmlspecialchars($u["nome"], ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        <?php echo $nivelU === 3 ? '⬇️ Remover Admin' : '⬆️ Tornar Admin'; ?>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-row-excluir btn-excluir-conta-admin"
                                        data-id="<?php echo $idM; ?>"
                                        data-nome="<?php echo htmlspecialchars($u["nome"], ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        🗑️ Remover
                                    </button>
                                <?php else: ?>
                                    <span class="texto-protegido">Sessão atual</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ABA 3: MODERAÇÃO DE CAMPANHAS -->
            <div class="admin-tab-conteudo" id="aba-campanhas">
                <div class="admin-lista">
                    <?php foreach ($listaCampanhas as $camp): ?>
                        <?php $ehAtiva = strtolower((string) $camp["status"]) === "ativa"; ?>
                        <article class="admin-row-card" data-campanha-id="<?php echo (int) $camp["idCampanha"]; ?>">
                            <div class="row-info">
                                <div class="row-titulo-linha">
                                    <h3><?php echo htmlspecialchars($camp["titulo"]); ?></h3>
                                    <span class="badge-status-camp <?php echo $ehAtiva ? 'ativa' : 'inativa'; ?>">
                                        <?php echo $ehAtiva ? '● Ativa' : '○ Inativa'; ?>
                                    </span>
                                </div>
                                <span>🏢 <?php echo htmlspecialchars($camp["nomeONG"]); ?> · 💰 R$ <?php echo number_format((float) $camp["arrecadado"], 2, ',', '.'); ?></span>
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
            </div>
        </section>

    </main>
</div>

<script>
(function () {
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

    document.addEventListener("click", async (e) => {
        const btnNivel = e.target.closest(".btn-alterar-nivel");
        if (btnNivel && !btnNivel.disabled) {
            const idMUsuario = Number(btnNivel.dataset.id);
            const novoNivel = Number(btnNivel.dataset.novoNivel);
            const nome = btnNivel.dataset.nome || "este usuário";
            if (!confirm(`Confirmar alteração de permissão para "${nome}"?`)) return;

            btnNivel.disabled = true;
            const resp = await fetch("painelAdmin.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ acao: "alterar_nivel", idMUsuario, novoNivel })
            });
            const dados = await resp.json();
            if (resp.ok && dados.sucesso) {
                window.location.reload();
            } else {
                alert(dados.erro || "Erro ao atualizar nível.");
                btnNivel.disabled = false;
            }
        }

        const btnConta = e.target.closest(".btn-excluir-conta-admin");
        if (btnConta && !btnConta.disabled) {
            const idMUsuario = Number(btnConta.dataset.id);
            const nome = btnConta.dataset.nome || "esta conta";
            if (!confirm(`Excluir permanentemente "${nome}" e todos os registros associados?`)) return;

            btnConta.disabled = true;
            const resp = await fetch("painelAdmin.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ acao: "excluir_usuario", idMUsuario })
            });
            const dados = await resp.json();
            if (resp.ok && dados.sucesso) {
                document.querySelectorAll(`[data-musuario-id="${idMUsuario}"]`).forEach((el) => el.remove());
            } else {
                alert(dados.erro || "Erro ao remover conta.");
                btnConta.disabled = false;
            }
        }

        const btnCamp = e.target.closest(".btn-excluir-campanha-admin");
        if (btnCamp && !btnCamp.disabled) {
            const idCampanha = Number(btnCamp.dataset.id);
            const titulo = btnCamp.dataset.titulo || "esta campanha";
            if (!confirm(`Excluir a campanha "${titulo}"?`)) return;

            btnCamp.disabled = true;
            const resp = await fetch("painelAdmin.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ acao: "excluir_campanha", idCampanha })
            });
            const dados = await resp.json();
            if (resp.ok && dados.sucesso) {
                document.querySelector(`[data-campanha-id="${idCampanha}"]`)?.remove();
            } else {
                alert(dados.erro || "Erro ao excluir campanha.");
                btnCamp.disabled = false;
            }
        }
    });
})();
</script>
</body>
</html>