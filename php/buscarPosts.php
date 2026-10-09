<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

try {

    $sql = "
        SELECT
            p.idPost,
            p.idONG,
            p.titulo,
            p.conteudo,
            p.descricao,
            p.dataPublicacao,
            p.palavrasChave,
            mu.nome AS nomeONG
        FROM posts p
        INNER JOIN ONGs o
            ON p.idONG = o.idONG
        INNER JOIN MoldeUsuario mu
            ON o.idMoldeUsuario = mu.idMUsuario
        ORDER BY p.dataPublicacao DESC
    ";

    $resultado = $conexao->query($sql);

    if (!$resultado) {
        throw new Exception("Erro ao consultar as publicações.");
    }

    $posts = [];

    while ($post = $resultado->fetch_assoc()) {
        $posts[] = $post;
    }

    echo json_encode(
        $posts,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

} catch (Throwable $e) {

    error_log("Erro em buscarPosts.php: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "erro" => "Erro ao buscar os posts."
    ], JSON_UNESCAPED_UNICODE);
}