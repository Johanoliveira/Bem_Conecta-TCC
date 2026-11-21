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
    responder(401, ["erro" => "Faça login para curtir publicações."]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(405, ["erro" => "Método não permitido."]);
}

$entrada = json_decode(file_get_contents("php://input"), true);
$idPost = filter_var($entrada["idPost"] ?? null, FILTER_VALIDATE_INT);

if (!$idPost || $idPost < 1) {
    responder(400, ["erro" => "Publicação inválida."]);
}

$idUsuario = (int) $_SESSION["usuario_id"];

try {
    // Confirma que a publicação existe.
    $stmt = $conexao->prepare(
        "SELECT idPost FROM posts WHERE idPost = ?"
    );
    $stmt->bind_param("i", $idPost);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        responder(404, ["erro" => "Publicação não encontrada."]);
    }
    $stmt->close();

    // Verifica se o usuário já curtiu.
    $stmt = $conexao->prepare(
        "SELECT idPost FROM curtidas WHERE idMUsuario = ? AND idPost = ?"
    );
    $stmt->bind_param("ii", $idUsuario, $idPost);
    $stmt->execute();
    $stmt->store_result();

    $jaCurtiu = $stmt->num_rows > 0;
    $stmt->close();

    if ($jaCurtiu) {
        $stmt = $conexao->prepare(
            "DELETE FROM curtidas WHERE idMUsuario = ? AND idPost = ?"
        );
        $stmt->bind_param("ii", $idUsuario, $idPost);
        $stmt->execute();
        $stmt->close();

        $curtido = false;
    } else {
        $stmt = $conexao->prepare(
            "INSERT INTO curtidas (idMUsuario, idPost) VALUES (?, ?)"
        );
        $stmt->bind_param("ii", $idUsuario, $idPost);
        $stmt->execute();
        $stmt->close();

        $curtido = true;
    }

    // Retorna a quantidade atual de curtidas.
    $stmt = $conexao->prepare(
        "SELECT COUNT(*) FROM curtidas WHERE idPost = ?"
    );
    $stmt->bind_param("i", $idPost);
    $stmt->execute();
    $stmt->bind_result($totalCurtidas);
    $stmt->fetch();
    $stmt->close();

    responder(200, [
        "curtido" => $curtido,
        "totalCurtidas" => (int) $totalCurtidas
    ]);

} catch (Throwable $e) {
    error_log("Erro ao curtir post: " . $e->getMessage());
    responder(500, ["erro" => "Não foi possível atualizar a curtida."]);
}