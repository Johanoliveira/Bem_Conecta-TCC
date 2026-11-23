<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexao.php";

try {
    // Atualiza para 'inativa' campanhas que já passaram da data limite
    $conexao->query("
        UPDATE campanhas
        SET status = 'inativa'
        WHERE status = 'ativa'
          AND dataFim IS NOT NULL
          AND dataFim <= NOW()
    ");

    $sql = "
        SELECT
            p.idPost,
            p.idONG,
            p.idCampanha,
            mu.nome AS nomeONG,
            mu.fotoPerfil AS fotoONG,
            p.titulo,
            p.conteudo,
            p.descricao,
            p.palavrasChave,
            p.dataPublicacao
        FROM posts p
        INNER JOIN ONGs o
            ON p.idONG = o.idONG
        INNER JOIN MoldeUsuario mu
            ON o.idMoldeUsuario = mu.idMUsuario
        LEFT JOIN campanhas c
            ON p.idCampanha = c.idCampanha
        WHERE p.idCampanha IS NULL
           OR (c.status = 'ativa' AND (c.dataFim IS NULL OR c.dataFim > NOW()))
        ORDER BY p.dataPublicacao DESC
    ";

    $resultado = $conexao->query($sql);

    if (!$resultado) {
        throw new Exception("Erro ao consultar as publicações: " . $conexao->error);
    }

    $posts = [];

    while ($post = $resultado->fetch_assoc()) {
        $fotoOng = trim((string) ($post["fotoONG"] ?? ""));

        if ($fotoOng === "" || in_array($fotoOng, ["perfil.png", "img/perfil/perfil.png"], true)) {
            $fotoOng = "../img/jpg/ftPerfil.jpg";
        } elseif (!str_starts_with($fotoOng, "../") && !str_starts_with($fotoOng, "http://") && !str_starts_with($fotoOng, "https://")) {
            $fotoOng = "../" . ltrim($fotoOng, "/");
        }

        $post["fotoONG"] = $fotoOng;
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
        "erro" => "Erro ao buscar os posts.",
        "detalhe" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}