<?php

header("Content-Type: application/json; charset=UTF-8");

require_once "conexao.php";

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
            o.nome AS nomeONG
        FROM Post p
        INNER JOIN ONG o ON p.idONG = o.idONG
        ORDER BY p.dataPublicacao DESC
    ";

    $stmt = $conexao->prepare($sql);
    $stmt->execute();

    $posts = $stmt->fetchAll();

    echo json_encode($posts, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        "erro" => "Erro ao buscar os posts."
    ]);
}