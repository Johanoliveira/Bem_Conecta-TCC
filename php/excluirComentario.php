<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set("display_errors", "0");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

function responderExclusao($status, $dados)
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION["usuario_id"])) {
    responderExclusao(401, ["sucesso" => false, "erro" => "Faça login para continuar."]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderExclusao(405, ["sucesso" => false, "erro" => "Método não permitido."]);
}

$entrada = json_decode(file_get_contents("php://input"), true);
$idComentario = filter_var($entrada["idComentario"] ?? null, FILTER_VALIDATE_INT);

if (!$idComentario || $idComentario < 1) {
    responderExclusao(400, ["sucesso" => false, "erro" => "Comentário inválido."]);
}

$usuarioId = (int) $_SESSION["usuario_id"];

try {
    // Busca o nível de segurança do usuário logado
    $stmtNivel = $conexao->prepare("SELECT nivelDeSeguranca FROM MoldeUsuario WHERE idMUsuario = ? LIMIT 1");
    $stmtNivel->bind_param("i", $usuarioId);
    $stmtNivel->execute();
    $userRow = $stmtNivel->get_result()->fetch_assoc();
    $stmtNivel->close();

    if (!$userRow) {
        responderExclusao(403, ["sucesso" => false, "erro" => "Usuário não encontrado."]);
    }

    $nivelSeguranca = (int) $userRow["nivelDeSeguranca"];

    // Busca o autor do comentário e a ONG dona da publicação
    $stmtInfo = $conexao->prepare("
        SELECT
            c.idComentario,
            uc.idMoldeUsuario AS autorMoldeId,
            o.idMoldeUsuario AS ongMoldeId
        FROM comentarios c
        INNER JOIN UsuarioComum uc
            ON uc.idUsuarioComum = c.idUsuarioComum
        INNER JOIN posts p
            ON p.idPost = c.idPost
        LEFT JOIN ONGs o
            ON o.idONG = p.idONG
        WHERE c.idComentario = ?
        LIMIT 1
    ");
    $stmtInfo->bind_param("i", $idComentario);
    $stmtInfo->execute();
    $info = $stmtInfo->get_result()->fetch_assoc();
    $stmtInfo->close();

    if (!$info) {
        responderExclusao(404, ["sucesso" => false, "erro" => "Comentário não encontrado."]);
    }

    $ehAutor = ((int) $info["autorMoldeId"] === $usuarioId);
    $ehOngDonaPost = ($info["ongMoldeId"] !== null && (int) $info["ongMoldeId"] === $usuarioId);
    $ehAdmin = ($nivelSeguranca === 3);

    if (!$ehAutor && !$ehOngDonaPost && !$ehAdmin) {
        responderExclusao(403, ["sucesso" => false, "erro" => "Você não tem permissão para excluir este comentário."]);
    }

    $stmtDel = $conexao->prepare("DELETE FROM comentarios WHERE idComentario = ?");
    $stmtDel->bind_param("i", $idComentario);
    $ok = $stmtDel->execute();
    $stmtDel->close();

    if (!$ok) {
        responderExclusao(500, ["sucesso" => false, "erro" => "Não foi possível excluir o comentário."]);
    }

    responderExclusao(200, ["sucesso" => true]);
} catch (Throwable $e) {
    error_log("Erro ao excluir comentário: " . $e->getMessage());
    responderExclusao(500, ["sucesso" => false, "erro" => "Erro interno: " . $e->getMessage()]);
}