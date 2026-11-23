<?php

session_start();

require_once "conexao.php";

// Verifica se existe usuário logado
if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

$idUsuario = (int) $_SESSION["usuario_id"];

// Busca os dados base do usuário
$sql = "SELECT idMUsuario, nome, email, telefone, fotoPerfil, dataCriacao, nivelDeSeguranca
        FROM MoldeUsuario
        WHERE idMUsuario = ?";

$stmt = $conexao->prepare($sql);
if (!$stmt) {
    die("Erro ao preparar consulta: " . $conexao->error);
}

$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    session_destroy();
    header("Location: ../html/login.html");
    exit;
}

$nivel = (int) ($usuario["nivelDeSeguranca"] ?? 1);

// Se for ONG (nivel >= 2), busca dados da tabela ONGs
$dadosOng = null;
$idONG = null;

$conexao->query("
    UPDATE campanhas
    SET status = 'inativa'
    WHERE status = 'ativa'
      AND dataFim IS NOT NULL
      AND dataFim <= NOW()
");

if ($nivel >= 2) {
    $stmtOng = $conexao->prepare("
        SELECT idONG, descricao, CNPJ, Website, instagram, facebook, linkX
        FROM ONGs
        WHERE idMoldeUsuario = ?
        LIMIT 1
    ");
    if ($stmtOng) {
        $stmtOng->bind_param("i", $idUsuario);
        $stmtOng->execute();
        $dadosOng = $stmtOng->get_result()->fetch_assoc();
        $stmtOng->close();

        if ($dadosOng) {
            $idONG = (int) $dadosOng["idONG"];
        }
    }
}

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
        $conexao->begin_transaction();

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

        // Se for ONG, atualiza também descrição e redes sociais
        if ($nivel >= 2 && $idONG !== null) {
            $novaDescricao = trim($_POST["descricaoOng"] ?? ($dadosOng["descricao"] ?? ""));
            $novoWebsite = trim($_POST["websiteOng"] ?? "");
            $novoInstagram = trim($_POST["instagramOng"] ?? "");
            $novoFacebook = trim($_POST["facebookOng"] ?? "");

            $sqlUpdateOng = "
                UPDATE ONGs
                SET descricao = ?, Website = ?, instagram = ?, facebook = ?
                WHERE idONG = ?
            ";
            $stmtUpdateOng = $conexao->prepare($sqlUpdateOng);
            if (!$stmtUpdateOng) {
                throw new Exception($conexao->error);
            }
            $stmtUpdateOng->bind_param("ssssi", $novaDescricao, $novoWebsite, $novoInstagram, $novoFacebook, $idONG);
            if (!$stmtUpdateOng->execute()) {
                throw new Exception($stmtUpdateOng->error);
            }
            $stmtUpdateOng->close();
        }

        $conexao->commit();

        if (isset($_SESSION["usuario_nome"])) {
            $_SESSION["usuario_nome"] = $novoNome;
        }

        echo json_encode([
            "sucesso" => true,
            "mensagem" => "Dados atualizados com sucesso!"
        ]);
    } catch (Throwable $e) {
        $conexao->rollback();
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Erro ao salvar no banco de dados: " . $e->getMessage()
        ]);
    }
    exit;
}

// =====================================
// DADOS ESPECÍFICOS: ONG vs DOADOR
// =====================================
$campanhasOng = [];
$postsOng = [];
$totalArrecadadoGeral = 0.0;
$atividadesRecentes = [];

if ($nivel >= 2 && $idONG !== null) {
    // Busca campanhas da ONG com total arrecadado em cada uma
    $sqlCamp = "
        SELECT
            c.idCampanha,
            c.titulo,
            c.descricao,
            c.meta,
            c.dataInicio,
            c.dataFim,
            c.status,
            COALESCE(SUM(d.valor), 0) AS totalArrecadado,
            COUNT(d.idDoacao) AS totalDoacoes
        FROM campanhas c
        LEFT JOIN doacoes d ON d.idCampanha = c.idCampanha
        WHERE c.idONG = ?
        GROUP BY c.idCampanha, c.titulo, c.descricao, c.meta, c.dataInicio, c.dataFim, c.status
        ORDER BY c.dataInicio DESC
    ";
    $stmtCamp = $conexao->prepare($sqlCamp);
    if ($stmtCamp) {
        $stmtCamp->bind_param("i", $idONG);
        $stmtCamp->execute();
        $resCamp = $stmtCamp->get_result();
        while ($row = $resCamp->fetch_assoc()) {
            $campanhasOng[] = $row;
            $totalArrecadadoGeral += (float) $row["totalArrecadado"];
        }
        $stmtCamp->close();
    }

    // Busca posts criados pela ONG
    $sqlPosts = "
        SELECT
            p.idPost,
            p.idCampanha,
            p.titulo,
            p.conteudo,
            p.descricao,
            p.dataPublicacao,
            c.titulo AS tituloCampanha,
            (SELECT COUNT(*) FROM curtidas cu WHERE cu.idPost = p.idPost) AS totalCurtidas,
            (SELECT COUNT(*) FROM comentarios co WHERE co.idPost = p.idPost) AS totalComentarios
        FROM posts p
        LEFT JOIN campanhas c ON p.idCampanha = c.idCampanha
        WHERE p.idONG = ?
          AND (p.idCampanha IS NULL OR (c.status = 'ativa' AND (c.dataFim IS NULL OR c.dataFim > NOW())))
        ORDER BY p.dataPublicacao DESC
    ";
    $stmtPosts = $conexao->prepare($sqlPosts);
    if ($stmtPosts) {
        $stmtPosts->bind_param("i", $idONG);
        $stmtPosts->execute();
        $resPosts = $stmtPosts->get_result();
        while ($row = $resPosts->fetch_assoc()) {
            $postsOng[] = $row;
        }
        $stmtPosts->close();
    }
} else {
    // Atividades recentes para usuário comum
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
    if ($stmtAtividades) {
        $idParam1 = (int) $idUsuario;
        $idParam2 = (int) $idUsuario;
        $stmtAtividades->bind_param("ii", $idParam1, $idParam2);
        $stmtAtividades->execute();
        $resultadoAtividades = $stmtAtividades->get_result();
        while ($atividade = $resultadoAtividades->fetch_assoc()) {
            $atividadesRecentes[] = $atividade;
        }
        $stmtAtividades->close();
    }
}

// Formatação dos dados
$nome = $usuario["nome"] ?: "-";
$email = $usuario["email"] ?: "-";
$telefone = $usuario["telefone"] ?: "-";
$foto = $usuario["fotoPerfil"];
$dataMembro = !empty($usuario["dataCriacao"]) ? date("d/m/Y", strtotime($usuario["dataCriacao"])) : "-";

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

    <link rel="stylesheet" href="../css/perfil.css?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/header.css">
</head>

<body>

    <!-- CABEÇALHO -->
    <?php require_once "header.php"; ?>

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="pagina">

        <!-- MENU LATERAL -->
        <aside class="sidebar">

            <div class="menu-lateral">
                <h3>Menu</h3>

                <a href="inicialPage.php">🏠 Página Inicial</a>
                <a href="minhasDoacoes.php">❤️ Minhas Doações</a>
                <a href="ongs.php">📌 ONGs Cadastradas</a>
                <a href="campanhas.php">📄 Campanhas</a>
                <a href="perfil.php" class="ativo">👤 Meu Perfil</a>
            </div>

            <?php if ($nivel >= 2): ?>
                <div class="meus-posts">
                    <a href="criarCampanha.php">
                        🎯 Criar Campanha
                    </a>
                </div>
                <div class="meus-posts">
                    <a href="criarPost.php">
                        ➕ Criar Post
                    </a>
                </div>
                <div class="meus-posts">
                    <a href="#meus-posts">
                        📰 Meus Posts
                    </a>
                </div>
            <?php endif; ?>

        </aside>

        <!-- ÁREA DO PERFIL -->
        <section class="conteudo-perfil">

            <!-- TÍTULO -->
            <div class="titulo-pagina">
                <h1><?php echo $nivel >= 2 ? "Perfil da ONG" : "Meu Perfil"; ?></h1>
                <p>
                    <?php echo $nivel >= 2
                        ? "Gerencie as informações da organização, acompanhe suas campanhas e publicações."
                        : "Gerencie suas informações e acompanhe seu impacto."; ?>
                </p>
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
                            <span id="dataCriacao"><?php echo htmlspecialchars($dataMembro); ?></span>
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

                <!-- INFORMAÇÕES PESSOAIS / DA ONG -->
                <div class="secao">

                    <h2><?php echo $nivel >= 2 ? "Informações da Organização" : "Informações Pessoais"; ?></h2>

                    <div class="grid-informacoes">

                        <div class="campo" id="campoNome">
                            <span><?php echo $nivel >= 2 ? "Nome da ONG" : "Nome completo"; ?></span>
                            <p id="nomeCompleto">
                                <?php echo htmlspecialchars($nome); ?>
                            </p>
                            <input
                                type="text"
                                id="inputNome"
                                class="input-edicao"
                                style="display: none;"
                                value="<?php echo htmlspecialchars($nome === '-' ? '' : $nome); ?>"
                                placeholder="Digite o nome"
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

                        <?php if ($nivel >= 2 && $dadosOng): ?>
                            <div class="campo">
                                <span>CNPJ</span>
                                <p><?php echo htmlspecialchars($dadosOng["CNPJ"] ?: "-"); ?></p>
                            </div>

                            <div class="campo" id="campoDescricaoOng" style="grid-column: 1 / -1;">
                                <span>Sobre a ONG</span>
                                <p id="descricaoOngTexto"><?php echo htmlspecialchars($dadosOng["descricao"] ?: "-"); ?></p>
                                <textarea
                                    id="inputDescricaoOng"
                                    class="input-edicao"
                                    rows="3"
                                    style="display: none;"
                                    placeholder="Descreva a atuação da ONG"
                                ><?php echo htmlspecialchars($dadosOng["descricao"] ?? ""); ?></textarea>
                            </div>

                            <div class="campo" id="campoWebsiteOng">
                                <span>Website</span>
                                <p id="websiteOngTexto"><?php echo htmlspecialchars($dadosOng["Website"] ?: "-"); ?></p>
                                <input
                                    type="url"
                                    id="inputWebsiteOng"
                                    class="input-edicao"
                                    style="display: none;"
                                    value="<?php echo htmlspecialchars($dadosOng["Website"] ?? ""); ?>"
                                    placeholder="https://..."
                                >
                            </div>

                            <div class="campo" id="campoInstagramOng">
                                <span>Instagram</span>
                                <p id="instagramOngTexto"><?php echo htmlspecialchars($dadosOng["instagram"] ?: "-"); ?></p>
                                <input
                                    type="text"
                                    id="inputInstagramOng"
                                    class="input-edicao"
                                    style="display: none;"
                                    value="<?php echo htmlspecialchars($dadosOng["instagram"] ?? ""); ?>"
                                    placeholder="@suaong ou link"
                                >
                            </div>
                        <?php else: ?>
                            <div class="campo" id="campoLocalizacao">
                                <span>Localização</span>
                                <p id="localizacaoUsuario">São Paulo, SP</p>
                            </div>
                        <?php endif; ?>

                    </div>

                </div>

                <?php if ($nivel === 1): ?>
                    <!-- INTERESSES (APENAS USUÁRIO COMUM) -->
                    <div class="secao interesses">
                        <h2>Causas que você apoia</h2>
                        <div class="tags" id="listaCausas">
                            <span>-</span>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- ESTATÍSTICAS -->
            <?php if ($nivel >= 2): ?>
                <div class="estatisticas">
                    <div class="card-estatistica">
                        <div class="icone verde">
                            <i class="fa-solid fa-hand-holding-heart"></i>
                        </div>
                        <div>
                            <span>Total Arrecadado</span>
                            <h2>R$ <?php echo number_format($totalArrecadadoGeral, 2, ",", "."); ?></h2>
                        </div>
                    </div>

                    <div class="card-estatistica">
                        <div class="icone">
                            <i class="fa-solid fa-bullseye"></i>
                        </div>
                        <div>
                            <span>Campanhas Criadas</span>
                            <h2><?php echo count($campanhasOng); ?></h2>
                        </div>
                    </div>

                    <div class="card-estatistica">
                        <div class="icone">
                            <i class="fa-solid fa-newspaper"></i>
                        </div>
                        <div>
                            <span>Publicações</span>
                            <h2><?php echo count($postsOng); ?></h2>
                        </div>
                    </div>
                </div>

                <!-- CAMPANHAS DA ONG -->
                <div class="atividade" id="minhas-campanhas">
                    <div class="cabecalho-secao-ong">
                        <h2>Minhas Campanhas</h2>
                        <div class="acoes-secao-campanhas">
                            <a href="criarCampanha.php" class="botao-editar" style="text-decoration: none;">
                                <i class="fa-solid fa-plus"></i> Criar Campanha
                            </a>
                            <?php if (!empty($campanhasOng)): ?>
                                <button type="button" id="btnEditarCampanhas" class="botao-editar-campanhas">
                                    <i class="fa-solid fa-pen"></i> Editar
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (empty($campanhasOng)): ?>
                        <div class="atividade-item">
                            <div class="atividade-icone">🎯</div>
                            <div>
                                <h3>Nenhuma campanha criada ainda</h3>
                                <p>Clique em "Criar Campanha" para iniciar sua primeira arrecadação com meta.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="lista-campanhas-ong">
                            <?php foreach ($campanhasOng as $camp): ?>
                                <?php
                                    $meta = (float) ($camp["meta"] ?? 0);
                                    $arrecadado = (float) $camp["totalArrecadado"];
                                    $porcentagem = $meta > 0 ? min(100, round(($arrecadado / $meta) * 100)) : 0;
                                    $expirou = !empty($camp["dataFim"]) && strtotime($camp["dataFim"]) <= time();
                                    $isInativa = ($camp["status"] === "inativa" || $expirou);
                                ?>
                                <div class="card-campanha-perfil <?php echo $isInativa ? 'campanha-inativa' : ''; ?>" data-campanha-id="<?php echo (int) $camp["idCampanha"]; ?>">
                                    <div class="topo-campanha-perfil">
                                        <h3><?php echo htmlspecialchars($camp["titulo"]); ?></h3>
                                        <span class="status-campanha <?php echo $isInativa ? 'inativa' : 'ativa'; ?>">
                                            <?php echo $isInativa ? "Inativa" : "Ativa"; ?>
                                        </span>
                                    </div>

                                    <p class="desc-campanha-perfil"><?php echo htmlspecialchars($camp["descricao"]); ?></p>

                                    <?php if (!empty($camp["dataFim"])): ?>
                                        <small class="prazo-campanha-perfil">
                                            <i class="fa-regular fa-clock"></i>
                                            Prazo limite: <?php echo date("d/m/Y", strtotime($camp["dataFim"])); ?>
                                        </small>
                                    <?php endif; ?>

                                    <div class="valores-campanha-perfil">
                                        <span>
                                            Arrecadado: <strong>R$ <?php echo number_format($arrecadado, 2, ",", "."); ?></strong>
                                            <?php if ($meta > 0): ?>
                                                de <strong>R$ <?php echo number_format($meta, 2, ",", "."); ?></strong> (<?php echo $porcentagem; ?>%)
                                            <?php endif; ?>
                                        </span>
                                        <small><?php echo (int) $camp["totalDoacoes"]; ?> doação(ões) recebida(s)</small>
                                    </div>

                                    <?php if ($meta > 0): ?>
                                        <div class="barra-progresso-container">
                                            <div class="barra-progresso-fill" style="width: <?php echo $porcentagem; ?>%;"></div>
                                        </div>
                                    <?php endif; ?>

                                    <button
                                        type="button"
                                        class="btn-excluir-campanha"
                                        data-campanha-id="<?php echo (int) $camp["idCampanha"]; ?>"
                                        style="display: none;"
                                    >
                                        <i class="fa-solid fa-trash"></i> Excluir Campanha
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- POSTS DA ONG -->
                <div class="atividade" id="meus-posts">
                    <div class="cabecalho-secao-ong">
                        <h2>Meus Posts</h2>
                        <a href="criarPost.php" class="botao-editar" style="text-decoration: none;">
                            <i class="fa-solid fa-plus"></i> Criar Post
                        </a>
                    </div>

                    <?php if (empty($postsOng)): ?>
                        <div class="atividade-item">
                            <div class="atividade-icone">📰</div>
                            <div>
                                <h3>Nenhuma publicação encontrada</h3>
                                <p>Compartilhe novidades da sua ONG com a comunidade clicando em "Criar Post".</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="lista-posts-perfil">
                            <?php foreach ($postsOng as $post): ?>
                                <article class="item-post-perfil" data-post-id="<?php echo (int) $post["idPost"]; ?>">
                                    <img
                                        src="<?php echo htmlspecialchars($post["conteudo"]); ?>"
                                        alt="<?php echo htmlspecialchars($post["titulo"]); ?>"
                                        class="thumb-post-perfil"
                                    >

                                    <div class="info-post-perfil">
                                        <div class="meta-post-perfil">
                                            <span><?php echo date("d/m/Y H:i", strtotime($post["dataPublicacao"])); ?></span>
                                            <?php if (!empty($post["tituloCampanha"])): ?>
                                                <span class="badge-campanha-post">🎯 <?php echo htmlspecialchars($post["tituloCampanha"]); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <h3><?php echo htmlspecialchars($post["titulo"]); ?></h3>
                                        <p><?php echo htmlspecialchars(mb_strimwidth($post["descricao"], 0, 160, "...")); ?></p>

                                        <div class="interacoes-post-perfil">
                                            <span>❤️ <?php echo (int) $post["totalCurtidas"]; ?> curtida(s)</span>
                                            <span>💬 <?php echo (int) $post["totalComentarios"]; ?> comentário(s)</span>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="btn-excluir-post-perfil"
                                        data-post-id="<?php echo (int) $post["idPost"]; ?>"
                                        title="Excluir publicação"
                                    >
                                        <i class="fa-solid fa-trash"></i> Excluir
                                    </button>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- ESTATÍSTICAS E ATIVIDADES DO DOADOR -->
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
            <?php endif; ?>

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

    <script src="../js/perfil.js?v=3"></script>

</body>

</html>