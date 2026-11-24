<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set("display_errors", "0");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

function responder($status, $dados)
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION["usuario_id"])) {
    responder(401, ["erro" => "Faça login para comentar."]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(405, ["erro" => "Método não permitido."]);
}

$entrada = json_decode(file_get_contents("php://input"), true);

$idPost = filter_var($entrada["idPost"] ?? null, FILTER_VALIDATE_INT);
$conteudo = trim($entrada["conteudo"] ?? "");

if (!$idPost || $idPost < 1) {
    responder(400, ["erro" => "Publicação inválida."]);
}

if ($conteudo === "" || mb_strlen($conteudo) > 500) {
    responder(400, ["erro" => "O comentário deve ter entre 1 e 500 caracteres."]);
}

$idUsuario = (int) $_SESSION["usuario_id"];

try {
    $stmt = $conexao->prepare(
        "SELECT idUsuarioComum
        FROM UsuarioComum
        WHERE idMoldeUsuario = ?"
    );
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $stmt->bind_result($idUsuarioComum);

    $temUsuarioComum = $stmt->fetch();
    $stmt->close();

    if (!$temUsuarioComum) {
        $stmt = $conexao->prepare(
            "INSERT INTO UsuarioComum (idMoldeUsuario)
            VALUES (?)"
        );
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();
        $idUsuarioComum = $stmt->insert_id;
        $stmt->close();
    }

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

    $stmt = $conexao->prepare(
        "INSERT INTO comentarios (idUsuarioComum, idPost, conteudo)
        VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iis", $idUsuarioComum, $idPost, $conteudo);
    $stmt->execute();
    $idComentario = $stmt->insert_id;
    $stmt->close();

    // Devolve o comentário salvo para exibição imediata com foto e permissão de exclusão.
    $stmt = $conexao->prepare(
        "SELECT
            c.idComentario,
            c.conteudo,
            c.dataComentario,
            mu.nome AS nomeUsuario,
            mu.fotoPerfil AS fotoUsuario
        FROM comentarios c
        INNER JOIN UsuarioComum uc
            ON uc.idUsuarioComum = c.idUsuarioComum
        INNER JOIN MoldeUsuario mu
            ON mu.idMUsuario = uc.idMoldeUsuario
        WHERE c.idComentario = ?"
    );
    $stmt->bind_param("i", $idComentario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $comentario = $resultado->fetch_assoc();
    $stmt->close();

    if ($comentario) {
        $foto = trim((string) ($comentario["fotoUsuario"] ?? ""));
        if ($foto === "" || in_array($foto, ["perfil.png", "img/perfil/perfil.png"], true)) {
            $comentario["fotoUsuario"] = "../img/jpg/ftPerfil.jpg";
        } elseif (strpos($foto, "../") !== 0 && strpos($foto, "http://") !== 0 && strpos($foto, "https://") !== 0) {
            $comentario["fotoUsuario"] = "../" . ltrim($foto, "/");
        }
        $comentario["podeExcluir"] = true;
    }

    responder(201, ["comentario" => $comentario]);

} catch (Throwable $e) {
    error_log("Erro ao salvar comentário: " . $e->getMessage());
    responder(500, ["erro" => "Não foi possível salvar o comentário."]);
}