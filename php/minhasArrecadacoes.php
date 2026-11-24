<?php
session_start();
require_once __DIR__ . "/conexao.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

$usuarioId = (int) $_SESSION["usuario_id"];

function normalizarFotoPerfil(?string $caminho): string
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

// 1. Verifica se o usuário logado está vinculado a uma ONG
$sqlOng = "
    SELECT
        o.idONG,
        mu.nome AS nomeONG,
        mu.fotoPerfil AS fotoONG,
        mu.nivelDeSeguranca
    FROM MoldeUsuario mu
    INNER JOIN ONGs o ON o.idMoldeUsuario = mu.idMUsuario
    WHERE mu.idMUsuario = ?
    LIMIT 1
";

$stmtOng = $conexao->prepare($sqlOng);
if (!$stmtOng) {
    http_response_code(500);
    exit("Erro ao carregar dados da organização.");
}

$stmtOng->bind_param("i", $usuarioId);
$stmtOng->execute();
$dadosOng = $stmtOng->get_result()->fetch_assoc();
$stmtOng->close();

// Se não for conta de ONG, redireciona para a página inicial
if (!$dadosOng) {
    header("Location: inicialPage.php");
    exit;
}

$idONG = (int) $dadosOng["idONG"];
$nomeONG = $dadosOng["nomeONG"];
$fotoONG = normalizarFotoPerfil($dadosOng["fotoONG"]);

// 2. Atualiza campanhas expiradas automaticamente
$conexao->query("
    UPDATE campanhas
    SET status = 'inativa'
    WHERE status = 'ativa'
      AND dataFim IS NOT NULL
      AND dataFim <= NOW()
");

// 3. Busca resumo geral de arrecadações da ONG
$totalArrecadado = 0.0;
$totalDoacoes = 0;
$doadoresUnicos = 0;
$maiorDoacao = 0.0;

$sqlResumo = "
    SELECT
        COALESCE(SUM(d.valor), 0) AS totalArrecadado,
        COUNT(d.idDoacao) AS totalDoacoes,
        COUNT(DISTINCT d.idUsuarioComum) AS doadoresUnicos,
        COALESCE(MAX(d.valor), 0) AS maiorDoacao
    FROM doacoes d
    INNER JOIN campanhas c ON c.idCampanha = d.idCampanha
    WHERE c.idONG = ?
";

$stmtResumo = $conexao->prepare($sqlResumo);
if ($stmtResumo) {
    $stmtResumo->bind_param("i", $idONG);
    $stmtResumo->execute();
    $resumo = $stmtResumo->get_result()->fetch_assoc();
    if ($resumo) {
        $totalArrecadado = (float) $resumo["totalArrecadado"];
        $totalDoacoes = (int) $resumo["totalDoacoes"];
        $doadoresUnicos = (int) $resumo["doadoresUnicos"];
        $maiorDoacao = (float) $resumo["maiorDoacao"];
    }
    $stmtResumo->close();
}

$ticketMedio = $totalDoacoes > 0 ? ($totalArrecadado / $totalDoacoes) : 0.0;

// 4. Busca desempenho individual de cada campanha da ONG
$campanhas = [];
$metaGlobal = 0.0;
$campanhasAtivas = 0;

$sqlCampanhas = "
    SELECT
        c.idCampanha,
        c.titulo,
        c.descricao,
        c.meta,
        c.dataInicio,
        c.dataFim,
        c.status,
        COALESCE(SUM(d.valor), 0) AS arrecadado,
        COUNT(d.idDoacao) AS qtdDoacoes,
        COUNT(DISTINCT d.idUsuarioComum) AS qtdDoadores
    FROM campanhas c
    LEFT JOIN doacoes d ON d.idCampanha = c.idCampanha
    WHERE c.idONG = ?
    GROUP BY c.idCampanha, c.titulo, c.descricao, c.meta, c.dataInicio, c.dataFim, c.status
    ORDER BY (c.status = 'ativa') DESC, arrecadado DESC, c.dataInicio DESC
";

$stmtCamp = $conexao->prepare($sqlCampanhas);
if ($stmtCamp) {
    $stmtCamp->bind_param("i", $idONG);
    $stmtCamp->execute();
    $resCamp = $stmtCamp->get_result();
    while ($row = $resCamp->fetch_assoc()) {
        $meta = (float) ($row["meta"] ?? 0);
        $arrecadado = (float) $row["arrecadado"];
        $row["meta_float"] = $meta;
        $row["arrecadado_float"] = $arrecadado;
        $row["percentual"] = $meta > 0 ? min(100, round(($arrecadado / $meta) * 100)) : 0;

        $metaGlobal += $meta;
        if (strtolower((string) $row["status"]) === "ativa") {
            $campanhasAtivas++;
        }

        $campanhas[] = $row;
    }
    $stmtCamp->close();
}

$percentualMetaGlobal = $metaGlobal > 0 ? min(100, round(($totalArrecadado / $metaGlobal) * 100)) : 0;

// 5. Busca ranking dos 5 maiores doadores da ONG
$topDoadores = [];
$sqlTopDoadores = "
    SELECT
        uc.idUsuarioComum,
        mu.nome AS nomeDoador,
        mu.fotoPerfil AS fotoDoador,
        COALESCE(SUM(d.valor), 0) AS totalContribuido,
        COUNT(d.idDoacao) AS qtdContribuicoes
    FROM doacoes d
    INNER JOIN campanhas c ON c.idCampanha = d.idCampanha
    INNER JOIN UsuarioComum uc ON uc.idUsuarioComum = d.idUsuarioComum
    INNER JOIN MoldeUsuario mu ON mu.idMUsuario = uc.idMoldeUsuario
    WHERE c.idONG = ?
    GROUP BY uc.idUsuarioComum, mu.nome, mu.fotoPerfil
    ORDER BY totalContribuido DESC, qtdContribuicoes DESC
    LIMIT 5
";

$stmtTop = $conexao->prepare($sqlTopDoadores);
if ($stmtTop) {
    $stmtTop->bind_param("i", $idONG);
    $stmtTop->execute();
    $resTop = $stmtTop->get_result();
    while ($row = $resTop->fetch_assoc()) {
        $row["foto_url"] = normalizarFotoPerfil($row["fotoDoador"]);
        $topDoadores[] = $row;
    }
    $stmtTop->close();
}

// 6. Busca histórico completo de doações recebidas
$doacoesRecebidas = [];
$sqlDoacoes = "
    SELECT
        d.idDoacao,
        d.valor,
        d.dataDoacao,
        c.idCampanha,
        c.titulo AS tituloCampanha,
        mu.nome AS nomeDoador,
        mu.email AS emailDoador,
        mu.fotoPerfil AS fotoDoador
    FROM doacoes d
    INNER JOIN campanhas c ON c.idCampanha = d.idCampanha
    INNER JOIN UsuarioComum uc ON uc.idUsuarioComum = d.idUsuarioComum
    INNER JOIN MoldeUsuario mu ON mu.idMUsuario = uc.idMoldeUsuario
    WHERE c.idONG = ?
    ORDER BY d.dataDoacao DESC, d.idDoacao DESC
";

$stmtDoacoes = $conexao->prepare($sqlDoacoes);
if ($stmtDoacoes) {
    $stmtDoacoes->bind_param("i", $idONG);$stmtDoacoes->execute();
    $resDoacoes =$stmtDoacoes->get_result();
    while ($row =$resDoacoes->fetch_assoc()) {
        $row["foto_url"] = normalizarFotoPerfil($row["fotoDoador"]);
        $doacoesRecebidas[] =$row;
    }
    $stmtDoacoes->close();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Arrecadações - Bem Conecta</title>
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/minhasArrecadacoes.css?v=1">
</head>
<body>

<?php require_once "header.php"; ?>

<div class="layout-com-sidebar">
    <?php require_once "sidebar.php"; ?>

    <main class="arrecadacoes-main">

        <!-- CABEÇALHO EM VIDRO LÍQUIDO -->
        <section class="vidro-card banner-arrecadacoes">
            <div class="banner-identidade">
                <img
                    src="<?php echo htmlspecialchars($fotoONG, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="Logo de <?php echo htmlspecialchars($nomeONG, ENT_QUOTES, 'UTF-8'); ?>"
                    class="banner-ong-avatar"
                    onerror="this.onerror=null; this.src='../img/jpg/ftPerfil.jpg';"
                >
                <div>
                    <span class="banner-selo">Painel Financeiro da ONG</span>
                    <h1>💰 Minhas Arrecadações</h1>
                    <p>
                        Acompanhe em tempo real os recursos recebidos por <strong><?php echo htmlspecialchars($nomeONG); ?></strong>,
                        o avanço das metas de cada campanha e o histórico de apoiadores.
                    </p>
                </div>
            </div>

            <div class="banner-acoes">
                <a href="criarCampanha.php" class="btn-primario-verde">
                    + Nova Campanha
                </a>
                <?php if (!empty($doacoesRecebidas)): ?>
                    <button type="button" id="btnExportarCsv" class="btn-secundario-vidro">
                        📥 Exportar CSV
                    </button>
                <?php endif; ?>
            </div>
        </section>

        <!-- 4 CARDS DE RESUMO -->
        <section class="resumo-grid" aria-label="Indicadores de Arrecadação">
            <article class="vidro-card card-kpi">
                <div class="kpi-topo">
                    <span class="kpi-icone">💰</span>
                    <span class="kpi-titulo">Total Arrecadado</span>
                </div>
                <strong class="kpi-valor destaque-verde">
                    R$<?php echo number_format($totalArrecadado, 2, ',', '.'); ?>
                </strong>
                <span class="kpi-sub">
                    <?php if ($metaGlobal > 0): ?>
                        <?php echo $percentualMetaGlobal; ?>% da meta global (R$<?php echo number_format($metaGlobal, 2, ',', '.'); ?>)
                    <?php else: ?>
                        Soma de todas as campanhas
                    <?php endif; ?>
                </span>
            </article>

            <article class="vidro-card card-kpi">
                <div class="kpi-topo">
                    <span class="kpi-icone">🧾</span>
                    <span class="kpi-titulo">Doações Recebidas</span>
                </div>
                <strong class="kpi-valor">
                    <?php echo $totalDoacoes; ?>
                </strong>
                <span class="kpi-sub">
                    Ticket médio: <strong>R$<?php echo number_format($ticketMedio, 2, ',', '.'); ?></strong>
                </span>
            </article>

            <article class="vidro-card card-kpi">
                <div class="kpi-topo">
                    <span class="kpi-icone">🤝</span>
                    <span class="kpi-titulo">Doadores Únicos</span>
                </div>
                <strong class="kpi-valor">
                    <?php echo $doadoresUnicos; ?>
                </strong>
                <span class="kpi-sub">
                    Maior doação: <strong>R$<?php echo number_format($maiorDoacao, 2, ',', '.'); ?></strong>
                </span>
            </article>

            <article class="vidro-card card-kpi">
                <div class="kpi-topo">
                    <span class="kpi-icone">🎯</span>
                    <span class="kpi-titulo">Campanhas Ativas</span>
                </div>
                <strong class="kpi-valor">
                    <?php echo $campanhasAtivas; ?> <small>/ <?php echo count($campanhas); ?></small>
                </strong>
                <span class="kpi-sub">
                    <?php echo count($campanhas) === 1 ? '1 campanha cadastrada' : count($campanhas) . ' campanhas cadastradas'; ?>
                </span>
            </article>
        </section>

        <!-- DESEMPENHO POR CAMPANHA + TOP APOIADORES -->
        <section class="painel-duplo">

            <!-- COLUNA ESQUERDA: CAMPANHAS DA ONG -->
            <div class="vidro-card bloco-secao">
                <div class="secao-cabecalho">
                    <div>
                        <h2>🎯 Arrecadação por Campanha</h2>
                        <p>Clique em "Filtrar doações" para ver apenas os apoios de uma campanha específica.</p>
                    </div>
                </div>

                <?php if (empty($campanhas)): ?>
                    <div class="estado-vazio">
                        <span class="vazio-icone">📢</span>
                        <h3>Nenhuma campanha criada ainda</h3>
                        <p>Crie sua primeira campanha para começar a receber doações e acompanhar as metas aqui.</p>
                        <a href="criarCampanha.php" class="btn-primario-verde">+ Criar Primeira Campanha</a>
                    </div>
                <?php else: ?>
                    <div class="lista-campanhas-arrecadacao">
                        <?php foreach ($campanhas as$camp): ?>
                            <?php
                                $ehAtiva = strtolower((string)$camp["status"]) === "ativa";
                                $dataFimFormatada = !empty($camp["dataFim"])
                                    ? "Encerra em " . date("d/m/Y", strtotime($camp["dataFim"]))
                                    : "Sem prazo definido";
                            ?>
                            <article class="item-campanha-card">
                                <div class="campanha-card-topo">
                                    <div class="campanha-card-titulos">
                                        <h3><?php echo htmlspecialchars($camp["titulo"]); ?></h3>
                                        <span class="campanha-prazo">📅 <?php echo $dataFimFormatada; ?> · 👥 <?php echo (int) $camp["qtdDoadores"]; ?> apoiador(es)</span>
                                    </div>
                                    <span class="badge-status <?php echo $ehAtiva ? 'ativa' : 'inativa'; ?>">
                                        <?php echo $ehAtiva ? '● Ativa' : '○ Encerrada'; ?>
                                    </span>
                                </div>

                                <div class="campanha-valores">
                                    <span>
                                        Arrecadado:
                                        <strong class="valor-destaque">R$<?php echo number_format($camp["arrecadado_float"], 2, ',', '.'); ?></strong>
                                        <?php if ($camp["meta_float"] > 0): ?>
                                            de <strong>R$<?php echo number_format($camp["meta_float"], 2, ',', '.'); ?></strong>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($camp["meta_float"] > 0): ?>
                                        <span class="campanha-pct"><?php echo (int) $camp["percentual"]; ?>%</span>
                                    <?php endif; ?>
                                </div>

                                <div
                                    class="barra-vidro-trilho"
                                    role="progressbar"
                                    aria-valuenow="<?php echo (int) $camp["percentual"]; ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                >
                                    <div
                                        class="barra-vidro-preenchimento"
                                        style="width: <?php echo (int) $camp["percentual"]; ?>%;"
                                    ></div>
                                </div>

                                <div class="campanha-card-acoes">
                                    <button
                                        type="button"
                                        class="btn-filtrar-campanha"
                                        data-campanha-id="<?php echo (int) $camp["idCampanha"]; ?>"
                                    >
                                        🔍 Filtrar doações (<?php echo (int) $camp["qtdDoacoes"]; ?>)
                                    </button>
                                    <a href="campanhas.php" class="link-ver-campanha">
                                        Ver campanhas →
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- COLUNA DIREITA: TOP APOIADORES -->
            <aside class="vidro-card bloco-secao bloco-apoiadores">
                <div class="secao-cabecalho">
                    <div>
                        <h2>🏆 Maiores Apoiadores</h2>
                        <p>Quem mais contribuiu com suas causas.</p>
                    </div>
                </div>

                <?php if (empty($topDoadores)): ?>
                    <div class="estado-vazio compacto">
                        <span class="vazio-icone">💚</span>
                        <p>Assim que as primeiras doações chegarem, o ranking dos apoiadores aparecerá aqui.</p>
                    </div>
                <?php else: ?>
                    <div class="lista-top-doadores">
                        <?php
                            $medalhas = ["🥇", "🥈", "🥉", "4º", "5º"];
                            foreach ($topDoadores as $idx =>$doador):
                        ?>
                            <div class="item-top-doador">
                                <span class="posicao-medalha"><?php echo $medalhas[$idx] ?? ($idx + 1) . "º"; ?></span>
                                <img
                                    src="<?php echo htmlspecialchars($doador["foto_url"], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="Foto de <?php echo htmlspecialchars($doador["nomeDoador"], ENT_QUOTES, 'UTF-8'); ?>"
                                    onerror="this.onerror=null; this.src='../img/jpg/ftPerfil.jpg';"
                                >
                                <div class="doador-ranking-info">
                                    <strong><?php echo htmlspecialchars($doador["nomeDoador"]); ?></strong>
                                    <span><?php echo (int) $doador["qtdContribuicoes"]; ?> <?php echo (int) $doador["qtdContribuicoes"] === 1 ? 'doação' : 'doações'; ?></span>
                                </div>
                                <span class="doador-ranking-valor">
                                    R$<?php echo number_format((float)$doador["totalContribuido"], 2, ',', '.'); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>

        </section>

        <!-- HISTÓRICO DETALHADO DE DOAÇÕES RECEBIDAS -->
        <section class="vidro-card bloco-secao" id="secaoHistoricoDoacoes">
            <div class="secao-cabecalho">
                <div>
                    <h2>📋 Histórico de Doações Recebidas</h2>
                    <p id="contadorResultadosDoacoes">
                        Exibindo <strong><?php echo count($doacoesRecebidas); ?></strong> registro(s)
                    </p>
                </div>
            </div>

            <?php if (empty($doacoesRecebidas)): ?>
                <div class="estado-vazio">
                    <span class="vazio-icone">📭</span>
                    <h3>Nenhuma doação recebida até o momento</h3>
                    <p>Divulgue suas campanhas no feed inicial para engajar novos doadores.</p>
                    <a href="criarPost.php" class="btn-primario-verde">+ Criar Publicação</a>
                </div>
            <?php else: ?>
                <!-- BARRA DE FILTROS -->
                <div class="filtros-barra">
                    <input
                        type="search"
                        id="filtroBuscaDoador"
                        class="filtro-input"
                        placeholder="🔎 Buscar por nome, e-mail ou campanha..."
                    >

                    <select id="filtroCampanhaSelect" class="filtro-select" aria-label="Filtrar por campanha">
                        <option value="todas">🎯 Todas as campanhas</option>
                        <?php foreach ($campanhas as$camp): ?>
                            <option value="<?php echo (int) $camp["idCampanha"]; ?>">
                                <?php echo htmlspecialchars($camp["titulo"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select id="filtroPeriodoSelect" class="filtro-select" aria-label="Filtrar por período">
                        <option value="todos">🗓️ Todo o período</option>
                        <option value="7">Últimos 7 dias</option>
                        <option value="30">Últimos 30 dias</option>
                        <option value="mes">Este mês</option>
                    </select>

                    <select id="filtroOrdemSelect" class="filtro-select" aria-label="Ordenar resultados">
                        <option value="recentes">⬇️ Mais recentes</option>
                        <option value="maior">💰 Maior valor</option>
                        <option value="menor">📉 Menor valor</option>
                    </select>
                </div>

                <!-- LISTA DE DOAÇÕES -->
                <div class="lista-doacoes-recebidas" id="listaDoacoesRecebidas">
                    <?php foreach ($doacoesRecebidas as$doacao): ?>
                        <?php
                            $timestamp = strtotime((string)$doacao["dataDoacao"]);
                            $dataFormatada = $timestamp ? date("d/m/Y \à\s H:i", $timestamp) : "-";
                            $dataIso = $timestamp ? date("Y-m-d", $timestamp) : "";
                            $valorFloat = (float)$doacao["valor"];
                            $textoBusca = mb_strtolower($doacao["nomeDoador"] . " " . $doacao["emailDoador"] . " " . $doacao["tituloCampanha"],
                                "UTF-8"
                            );
                        ?>
                        <article
                            class="item-doacao-row"
                            data-id-doacao="<?php echo (int) $doacao["idDoacao"]; ?>"
                            data-campanha-id="<?php echo (int) $doacao["idCampanha"]; ?>"
                            data-valor="<?php echo number_format($valorFloat, 2, '.', ''); ?>"
                            data-timestamp="<?php echo (int) $timestamp; ?>"
                            data-data-iso="<?php echo htmlspecialchars($dataIso, ENT_QUOTES, 'UTF-8'); ?>"
                            data-busca="<?php echo htmlspecialchars($textoBusca, ENT_QUOTES, 'UTF-8'); ?>"
                            data-csv-doador="<?php echo htmlspecialchars($doacao["nomeDoador"], ENT_QUOTES, 'UTF-8'); ?>"
                            data-csv-email="<?php echo htmlspecialchars($doacao["emailDoador"], ENT_QUOTES, 'UTF-8'); ?>"
                            data-csv-campanha="<?php echo htmlspecialchars($doacao["tituloCampanha"], ENT_QUOTES, 'UTF-8'); ?>"
                            data-csv-data="<?php echo htmlspecialchars($dataFormatada, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <div class="doacao-doador">
                                <img
                                    src="<?php echo htmlspecialchars($doacao["foto_url"], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="Foto de <?php echo htmlspecialchars($doacao["nomeDoador"], ENT_QUOTES, 'UTF-8'); ?>"
                                    onerror="this.onerror=null; this.src='../img/jpg/ftPerfil.jpg';"
                                >
                                <div class="doacao-doador-dados">
                                    <strong><?php echo htmlspecialchars($doacao["nomeDoador"]); ?></strong>
                                    <span><?php echo htmlspecialchars($doacao["emailDoador"]); ?></span>
                                </div>
                            </div>

                            <div class="doacao-campanha-info">
                                <span class="badge-campanha-doacao">
                                    🎯 <?php echo htmlspecialchars($doacao["tituloCampanha"]); ?>
                                </span>
                                <span class="doacao-data">
                                    🕒 <?php echo $dataFormatada; ?>
                                </span>
                            </div>

                            <div class="doacao-valor-pill">
                                + R$<?php echo number_format($valorFloat, 2, ',', '.'); ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="estado-vazio" id="avisoFiltroVazio" style="display: none;">
                    <span class="vazio-icone">🔎</span>
                    <h3>Nenhuma doação encontrada com esses filtros</h3>
                    <p>Tente limpar a busca ou selecionar outro período.</p>
                    <button type="button" id="btnLimparFiltros" class="btn-primario-verde">Limpar Filtros</button>
                </div>
            <?php endif; ?>
        </section>

    </main>
</div>

<script>
(function () {
    const inputBusca = document.getElementById("filtroBuscaDoador");
    const selectCampanha = document.getElementById("filtroCampanhaSelect");
    const selectPeriodo = document.getElementById("filtroPeriodoSelect");
    const selectOrdem = document.getElementById("filtroOrdemSelect");
    const listaContainer = document.getElementById("listaDoacoesRecebidas");
    const avisoVazio = document.getElementById("avisoFiltroVazio");
    const contadorEl = document.getElementById("contadorResultadosDoacoes");
    const btnLimpar = document.getElementById("btnLimparFiltros");
    const btnExportarCsv = document.getElementById("btnExportarCsv");

    if (!listaContainer) return;

    const itens = Array.from(listaContainer.querySelectorAll(".item-doacao-row"));

    function aplicarFiltros() {
        const termo = (inputBusca?.value || "").trim().toLowerCase();
        const campanhaId = selectCampanha?.value || "todas";
        const periodo = selectPeriodo?.value || "todos";
        const ordem = selectOrdem?.value || "recentes";

        const agora = new Date();
        const inicioMes = new Date(agora.getFullYear(), agora.getMonth(), 1).getTime() / 1000;
        const agoraSegundos = Math.floor(agora.getTime() / 1000);

        const filtrados = itens.filter((item) => {
            const texto = item.dataset.busca || "";
            const itemCamp = item.dataset.campanhaId || "";
            const ts = Number(item.dataset.timestamp || 0);

            if (termo !== "" && !texto.includes(termo)) return false;
            if (campanhaId !== "todas" && itemCamp !== campanhaId) return false;

            if (periodo === "7" && ts < agoraSegundos - 7 * 86400) return false;
            if (periodo === "30" && ts < agoraSegundos - 30 * 86400) return false;
            if (periodo === "mes" && ts < inicioMes) return false;

            return true;
        });

        filtrados.sort((a, b) => {
            if (ordem === "maior") {
                return Number(b.dataset.valor) - Number(a.dataset.valor);
            }
            if (ordem === "menor") {
                return Number(a.dataset.valor) - Number(b.dataset.valor);
            }
            return Number(b.dataset.timestamp) - Number(a.dataset.timestamp);
        });

        itens.forEach((item) => {
            item.style.display = "none";
        });

        let somaFiltrada = 0;
        filtrados.forEach((item) => {
            item.style.display = "flex";
            listaContainer.appendChild(item);
            somaFiltrada += Number(item.dataset.valor || 0);
        });

        if (avisoVazio) {
            avisoVazio.style.display = filtrados.length === 0 ? "flex" : "none";
        }

        if (contadorEl) {
            const valorFormatado = somaFiltrada.toLocaleString("pt-BR", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            contadorEl.innerHTML = `Exibindo <strong>${filtrados.length}</strong> registro(s) · Subtotal filtrado: <strong>R$ ${valorFormatado}</strong>`;
        }
    }

    inputBusca?.addEventListener("input", aplicarFiltros);
    selectCampanha?.addEventListener("change", aplicarFiltros);
    selectPeriodo?.addEventListener("change", aplicarFiltros);
    selectOrdem?.addEventListener("change", aplicarFiltros);

    // Atalho nos cards de campanha para filtrar o histórico abaixo
    document.querySelectorAll(".btn-filtrar-campanha").forEach((btn) => {
        btn.addEventListener("click", () => {
            if (!selectCampanha) return;
            selectCampanha.value = btn.dataset.campanhaId || "todas";
            aplicarFiltros();
            document.getElementById("secaoHistoricoDoacoes")?.scrollIntoView({ behavior: "smooth" });
        });
    });

    btnLimpar?.addEventListener("click", () => {
        if (inputBusca) inputBusca.value = "";
        if (selectCampanha) selectCampanha.value = "todas";
        if (selectPeriodo) selectPeriodo.value = "todos";
        if (selectOrdem) selectOrdem.value = "recentes";
        aplicarFiltros();
    });

    // Exportar registros visíveis para CSV (compatível com Excel)
    btnExportarCsv?.addEventListener("click", () => {
        const visiveis = itens.filter((item) => item.style.display !== "none");
        if (visiveis.length === 0) {
            alert("Não há registros visíveis para exportar.");
            return;
        }

        const linhas = [
            ["ID Doação", "Doador", "E-mail", "Campanha", "Data", "Valor (R$)"]
        ];

        visiveis.forEach((item) => {
            const valorBR = Number(item.dataset.valor || 0).toFixed(2).replace(".", ",");
            linhas.push([
                item.dataset.idDoacao || "",
                `"${(item.dataset.csvDoador || "").replace(/"/g, '""')}"`,
                `"${(item.dataset.csvEmail || "").replace(/"/g, '""')}"`,
                `"${(item.dataset.csvCampanha || "").replace(/"/g, '""')}"`,
                `"${(item.dataset.csvData || "").replace(/"/g, '""')}"`,
                `"${valorBR}"`
            ]);
        });

        const csvConteudo = "\uFEFF" + linhas.map((l) => l.join(";")).join("\n");
        const blob = new Blob([csvConteudo], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);

        const link = document.createElement("a");
        link.href = url;
        link.download = `arrecadacoes_bem_conecta_${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    });

    aplicarFiltros();
})();
</script>
</body>
</html>