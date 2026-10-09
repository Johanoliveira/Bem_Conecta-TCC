<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

function responder($status, $dados) {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION["usuario_id"])) {
    responder(401, ["erro" => "Faça login novamente."]);
}

$idUsuario = (int) $_SESSION["usuario_id"];
$idPost = filter_input(INPUT_GET, "idPost", FILTER_VALIDATE_INT);

if (!$idPost || $idPost < 1) {
    responder(400, ["erro" => "Publicação inválida."]);
}

try {
    $stmt = $conexao->prepare(
        "SELECT COUNT(*) FROM curtidas WHERE idPost = ?"
    );
    $stmt->bind_param("i", $idPost);
    $stmt->execute();
    $stmt->bind_result($totalCurtidas);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conexao->prepare(
        "SELECT idPost FROM curtidas WHERE idMUsuario = ? AND idPost = ?"
    );
    $stmt->bind_param("ii", $idUsuario, $idPost);
    $stmt->execute();
    $stmt->store_result();
    $curtido = $stmt->num_rows > 0;
    $stmt->close();

    $stmt = $conexao->prepare(
        "SELECT
            c.idComentario,
            c.conteudo,
            c.dataComentario,
            mu.nome AS nomeUsuario
        FROM comentarios c
        INNER JOIN UsuarioComum uc
            ON uc.idUsuarioComum = c.idUsuarioComum
        INNER JOIN MoldeUsuario mu
            ON mu.idMUsuario = uc.idMoldeUsuario
        WHERE c.idPost = ?
        ORDER BY c.dataComentario ASC, c.idComentario ASC"
    );
    $stmt->bind_param("i", $idPost);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $comentarios = [];

    while ($comentario = $resultado->fetch_assoc()) {
        $comentarios[] = $comentario;
    }

    $stmt->close();

    responder(200, [
        "totalCurtidas" => (int) $totalCurtidas,
        "curtido" => $curtido,
        "comentarios" => $comentarios
    ]);

} catch (Throwable $e) {
    error_log("Erro ao buscar interações: " . $e->getMessage());
    responder(500, ["erro" => "Não foi possível carregar as interações."]);
}