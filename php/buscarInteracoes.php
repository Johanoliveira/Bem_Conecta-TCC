<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set("display_errors", "0");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

function responderInteracoes($status, $dados)
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizarCaminhoAvatar($caminho)
{
    $foto = trim((string) $caminho);
    if ($foto === "" || in_array($foto, ["perfil.png", "img/perfil/perfil.png"], true)) {
        return "../img/jpg/ftPerfil.jpg";
    }
    if (strpos($foto, "../") === 0 || strpos($foto, "http://") === 0 || strpos($foto, "https://") === 0) {
        return $foto;
    }
    return "../" . ltrim($foto, "/");
}

$idPost = filter_input(INPUT_GET, "idPost", FILTER_VALIDATE_INT);

if (!$idPost || $idPost < 1) {
    responderInteracoes(400, ["erro" => "Publicação inválida."]);
}

$usuarioLogadoId = (int) ($_SESSION["usuario_id"] ?? 0);
$nivelLogado = 1;

try {
    if ($usuarioLogadoId > 0) {
        $stmtNivel = $conexao->prepare("SELECT nivelDeSeguranca FROM MoldeUsuario WHERE idMUsuario = ? LIMIT 1");
        $stmtNivel->bind_param("i", $usuarioLogadoId);
        $stmtNivel->execute();
        $resNivel = $stmtNivel->get_result()->fetch_assoc();
        $nivelLogado = (int) ($resNivel["nivelDeSeguranca"] ?? 1);
        $stmtNivel->close();
    }

    // 1. Total de curtidas do post
    $stmtTotal = $conexao->prepare("SELECT COUNT(*) AS total FROM curtidas WHERE idPost = ?");
    $stmtTotal->bind_param("i", $idPost);
    $stmtTotal->execute();
    $resTotal = $stmtTotal->get_result()->fetch_assoc();
    $totalCurtidas = (int) ($resTotal["total"] ?? 0);
    $stmtTotal->close();

    // 2. Verifica se o usuário logado já curtiu este post
    $curtido = false;
    if ($usuarioLogadoId > 0) {
        $stmtCurtido = $conexao->prepare("SELECT 1 FROM curtidas WHERE idMUsuario = ? AND idPost = ? LIMIT 1");
        $stmtCurtido->bind_param("ii", $usuarioLogadoId, $idPost);
        $stmtCurtido->execute();
        $curtido = $stmtCurtido->get_result()->num_rows > 0;
        $stmtCurtido->close();
    }

    // 3. Lista de comentários com foto e permissão de exclusão
    $stmtComentarios = $conexao->prepare("
        SELECT
            c.idComentario,
            c.conteudo,
            c.dataComentario,
            mu.idMUsuario AS idAutorUsuario,
            mu.nome AS nomeUsuario,
            mu.fotoPerfil AS fotoUsuario,
            o.idMoldeUsuario AS idOngDonaUsuario
        FROM comentarios c
        INNER JOIN UsuarioComum uc
            ON uc.idUsuarioComum = c.idUsuarioComum
        INNER JOIN MoldeUsuario mu
            ON mu.idMUsuario = uc.idMoldeUsuario
        INNER JOIN posts p
            ON p.idPost = c.idPost
        LEFT JOIN ONGs o
            ON o.idONG = p.idONG
        WHERE c.idPost = ?
        ORDER BY c.dataComentario ASC, c.idComentario ASC
    ");
    $stmtComentarios->bind_param("i", $idPost);
    $stmtComentarios->execute();
    $resComentarios = $stmtComentarios->get_result();

    $comentarios = [];
    while ($row = $resComentarios->fetch_assoc()) {
        $ehAutor = ((int) $row["idAutorUsuario"] === $usuarioLogadoId);
        $ehOngDona = ($row["idOngDonaUsuario"] !== null && (int) $row["idOngDonaUsuario"] === $usuarioLogadoId);

        $comentarios[] = [
            "idComentario" => (int) $row["idComentario"],
            "conteudo" => $row["conteudo"],
            "dataComentario" => $row["dataComentario"],
            "nomeUsuario" => $row["nomeUsuario"],
            "fotoUsuario" => normalizarCaminhoAvatar($row["fotoUsuario"]),
            "podeExcluir" => ($usuarioLogadoId > 0) && ($ehAutor || $ehOngDona || $nivelLogado === 3)
        ];
    }
    $stmtComentarios->close();

    responderInteracoes(200, [
        "totalCurtidas" => $totalCurtidas,
        "curtido" => $curtido,
        "comentarios" => $comentarios
    ]);
} catch (Throwable $e) {
    error_log("Erro ao carregar interações: " . $e->getMessage());
    responderInteracoes(500, ["erro" => "Erro ao carregar interações."]);
}