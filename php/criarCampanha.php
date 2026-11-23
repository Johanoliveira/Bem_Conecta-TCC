<?php
session_start();

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../html/login.html");
    exit;
}

require_once __DIR__ . "/conexao.php";

$usuarioId = (int) $_SESSION["usuario_id"];
$erro = "";

// Busca nível de segurança e idONG do usuário logado
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
    http_response_code(500);
    exit("Erro ao carregar dados do usuário.");
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
$idONG = $usuario["idONG"] !== null ? (int) $usuario["idONG"] : null;

if ($nivel === 1) {
    http_response_code(403);
    exit("Apenas ONGs podem criar campanhas.");
}

// Se for administrador (nível 3) sem ONG própria, carrega lista de ONGs para vincular
$listaOngs = [];
if ($nivel === 3 && $idONG === null) {
    $resOngs = $conexao->query("
        SELECT o.idONG, mu.nome
        FROM ONGs o
        INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
        ORDER BY mu.nome ASC
    ");
    if ($resOngs) {
        while ($row = $resOngs->fetch_assoc()) {
            $listaOngs[] = $row;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $titulo = trim($_POST["titulo"] ?? "");
    $descricao = trim($_POST["descricao"] ?? "");
    $metaInput = str_replace(",", ".", trim($_POST["meta"] ?? ""));
    $dataFimInput = trim($_POST["dataFim"] ?? "");

    $idOngDestino = $idONG;
    if ($nivel === 3 && $idOngDestino === null) {
        $idOngDestino = (int) ($_POST["idONG"] ?? 0);
    }

    if ($idOngDestino === null || $idOngDestino <= 0) {
        $erro = "Sua conta não está vinculada a uma ONG válida.";
    } elseif ($titulo === "" || $descricao === "") {
        $erro = "Preencha o título e a descrição da campanha.";
    } elseif (mb_strlen($titulo) > 100) {
        $erro = "O título deve ter no máximo 100 caracteres.";
    } elseif ($metaInput !== "" && (!is_numeric($metaInput) || (float) $metaInput <= 0)) {
        $erro = "Informe um valor de meta válido (maior que zero) ou deixe em branco.";
    } else {
        $metaValor = ($metaInput !== "") ? number_format((float) $metaInput, 2, ".", "") : null;
        $dataFimValor = null;

        if ($dataFimInput !== "") {
            $dataObj = DateTime::createFromFormat("Y-m-d", $dataFimInput);
            if (!$dataObj) {
                $erro = "Data de encerramento inválida.";
            } else {
                $dataFimValor = $dataObj->format("Y-m-d 23:59:59");
            }
        }

        if ($erro === "") {
            $sqlInsert = "
                INSERT INTO campanhas (idONG, titulo, descricao, meta, dataFim, status)
                VALUES (?, ?, ?, ?, ?, 'ativa')
            ";
            $stmtInsert = $conexao->prepare($sqlInsert);

            if (!$stmtInsert) {
                $erro = "Erro ao preparar cadastro da campanha: " . $conexao->error;
            } else {
                $stmtInsert->bind_param("issss", $idOngDestino, $titulo, $descricao, $metaValor, $dataFimValor);

                if ($stmtInsert->execute()) {
                    $stmtInsert->close();
                    header("Location: perfil.php?campanha=criada");
                    exit;
                } else {
                    $erro = "Erro ao salvar campanha: " . $stmtInsert->error;
                    $stmtInsert->close();
                }
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
    <title>Criar Campanha - Bem Conecta</title>
    <link rel="stylesheet" href="../css/criarPost.css">
</head>
<body>
<main class="post-container">

    <a href="perfil.php" class="back-link">
        ← Voltar ao perfil
    </a>

    <section class="post-card">
        <h1>Criar nova campanha</h1>
        <p class="subtitle">
            Defina uma meta de arrecadação para que os doadores acompanhem o progresso da sua causa.
        </p>

        <?php if ($erro !== ""): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($erro, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="criarCampanha.php">

            <?php if ($nivel === 3 && $idONG === null): ?>
                <label for="idONG">ONG responsável</label>
                <select id="idONG" name="idONG" required>
                    <option value="">Selecione a ONG...</option>
                    <?php foreach ($listaOngs as $ongItem): ?>
                        <option value="<?php echo (int) $ongItem["idONG"]; ?>">
                            <?php echo htmlspecialchars($ongItem["nome"], ENT_QUOTES, "UTF-8"); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label for="titulo">Título da campanha</label>
            <input
                type="text"
                id="titulo"
                name="titulo"
                maxlength="100"
                required
                value="<?php echo htmlspecialchars($_POST["titulo"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                placeholder="Ex.: Reforma da Biblioteca Comunitária"
            >

            <label for="meta">Meta de arrecadação em R$ (opcional)</label>
            <input
                type="number"
                id="meta"
                name="meta"
                step="0.01"
                min="1"
                value="<?php echo htmlspecialchars($_POST["meta"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
                placeholder="Ex.: 5000.00"
            >

            <label for="dataFim">Data limite da campanha (opcional)</label>
            <input
                type="date"
                id="dataFim"
                name="dataFim"
                value="<?php echo htmlspecialchars($_POST["dataFim"] ?? "", ENT_QUOTES, "UTF-8"); ?>"
            >

            <label for="descricao">Descrição e objetivo da campanha</label>
            <textarea
                id="descricao"
                name="descricao"
                rows="6"
                required
                placeholder="Explique como os recursos arrecadados serão utilizados..."
            ><?php echo htmlspecialchars($_POST["descricao"] ?? "", ENT_QUOTES, "UTF-8"); ?></textarea>

            <button type="submit" class="publish-btn">
                Criar Campanha
            </button>

        </form>
    </section>

</main>
</body>
</html>