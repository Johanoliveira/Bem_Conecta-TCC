<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["usuario_id"])) {
    http_response_code(401);
    echo json_encode(["sucesso" => false, "erro" => "Usuário não autenticado."]);
    exit;
}

require_once __DIR__ . "/conexao.php";

$dados = json_decode(file_get_contents("php://input"), true);
$idPost = (int) ($dados["idPost"] ?? $_POST["idPost"] ?? 0);
$usuarioId = (int) $_SESSION["usuario_id"];

if ($idPost <= 0) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "Publicação inválida."]);
    exit;
}

try {
    // 1. Busca nível de segurança e idONG do usuário logado
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

    $stmtUser = $conexao->prepare($sqlUsuario);
    $stmtUser->bind_param("i", $usuarioId);
    $stmtUser->execute();
    $usuario = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    if (!$usuario) {
        http_response_code(403);
        echo json_encode(["sucesso" => false, "erro" => "Usuário não encontrado."]);
        exit;
    }

    $nivel = (int) $usuario["nivelDeSeguranca"];
    $idOngUsuario = $usuario["idONG"] !== null ? (int) $usuario["idONG"] : null;

    if ($nivel === 1) {
        http_response_code(403);
        echo json_encode(["sucesso" => false, "erro" => "Você não tem permissão para excluir publicações."]);
        exit;
    }

    // 2. Busca o post e verifica se pertence à ONG (ou libera se for Administrador nível 3)
    if ($nivel === 2) {
        if ($idOngUsuario === null) {
            http_response_code(403);
            echo json_encode(["sucesso" => false, "erro" => "Sua conta não está vinculada a uma ONG."]);
            exit;
        }

        $stmtPost = $conexao->prepare("SELECT conteudo FROM posts WHERE idPost = ? AND idONG = ? LIMIT 1");
        $stmtPost->bind_param("ii", $idPost, $idOngUsuario);
    } else {
        $stmtPost = $conexao->prepare("SELECT conteudo FROM posts WHERE idPost = ? LIMIT 1");
        $stmtPost->bind_param("i", $idPost);
    }

    $stmtPost->execute();
    $post = $stmtPost->get_result()->fetch_assoc();
    $stmtPost->close();

    if (!$post) {
        http_response_code(403);
        echo json_encode(["sucesso" => false, "erro" => "Publicação não encontrada ou sem permissão para excluir."]);
        exit;
    }

    $caminhoImagem = $post["conteudo"] ?? "";

    // 3. Remove curtidas, comentários e o post em transação
    $conexao->begin_transaction();

    $stmtCurtidas = $conexao->prepare("DELETE FROM curtidas WHERE idPost = ?");
    $stmtCurtidas->bind_param("i", $idPost);
    $stmtCurtidas->execute();
    $stmtCurtidas->close();

    $stmtComentarios = $conexao->prepare("DELETE FROM comentarios WHERE idPost = ?");
    $stmtComentarios->bind_param("i", $idPost);
    $stmtComentarios->execute();
    $stmtComentarios->close();

    $stmtDelete = $conexao->prepare("DELETE FROM posts WHERE idPost = ?");
    $stmtDelete->bind_param("i", $idPost);
    $stmtDelete->execute();
    $stmtDelete->close();

    $conexao->commit();

    // 4. Apaga a imagem física da pasta ../uploadFoto/posts/ (e mantém compatibilidade com ../img/posts/)
    if (strpos($caminhoImagem, "../uploadFoto/posts/") === 0) {
        $arquivoFisico = __DIR__ . "/../uploadFoto/posts/" . basename($caminhoImagem);
        if (is_file($arquivoFisico)) {
            unlink($arquivoFisico);
        }
    } elseif (strpos($caminhoImagem, "../img/posts/") === 0) {
        $arquivoFisico = __DIR__ . "/../img/posts/" . basename($caminhoImagem);
        if (is_file($arquivoFisico)) {
            unlink($arquivoFisico);
        }
    }

    echo json_encode(["sucesso" => true]);

} catch (Throwable $e) {
    if ($conexao->errno) {
        $conexao->rollback();
    }
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Erro ao excluir publicação: " . $e->getMessage()]);
}