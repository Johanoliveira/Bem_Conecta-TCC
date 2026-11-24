<?php
ini_set("display_errors", "0");
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/conexao.php";

function normalizarCaminhoFeed($caminho, $padrao)
{
    $valor = trim((string) $caminho);
    if ($valor === "" || in_array($valor, ["perfil.png", "img/perfil/perfil.png"], true)) {
        return $padrao;
    }
    if (strpos($valor, "../") === 0 || strpos($valor, "http://") === 0 || strpos($valor, "https://") === 0) {
        return $valor;
    }
    return "../" . ltrim($valor, "/");
}

try {
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
            p.idMUsuario,
            p.idCampanha,
            COALESCE(muOng.nome, CONCAT(muAdmin.nome, ' (Admin)'), 'Bem Conecta') AS nomeONG,
            COALESCE(muOng.fotoPerfil, muAdmin.fotoPerfil, 'img/jpg/ftPerfil.jpg') AS fotoONG,
            p.titulo,
            p.conteudo,
            p.descricao,
            p.palavrasChave,
            p.dataPublicacao
        FROM posts p
        LEFT JOIN ONGs o
            ON o.idONG = p.idONG
        LEFT JOIN MoldeUsuario muOng
            ON muOng.idMUsuario = o.idMoldeUsuario
        LEFT JOIN MoldeUsuario muAdmin
            ON muAdmin.idMUsuario = p.idMUsuario
        LEFT JOIN campanhas c
            ON c.idCampanha = p.idCampanha
        WHERE (
            p.idCampanha IS NULL
            OR (c.status = 'ativa' AND (c.dataFim IS NULL OR c.dataFim > NOW()))
        )
        ORDER BY p.dataPublicacao DESC, p.idPost DESC
    ";

    $resultado = $conexao->query($sql);

    if (!$resultado) {
        throw new Exception($conexao->error);
    }

    $posts = [];
    while ($row = $resultado->fetch_assoc()) {
        $row["idPost"] = (int) $row["idPost"];
        $row["idONG"] = $row["idONG"] !== null ? (int) $row["idONG"] : null;
        $row["idCampanha"] = $row["idCampanha"] !== null ? (int) $row["idCampanha"] : null;
        $row["fotoONG"] = normalizarCaminhoFeed($row["fotoONG"], "../img/jpg/ftPerfil.jpg");
        $row["conteudo"] = normalizarCaminhoFeed($row["conteudo"], "../img/jpg/holder.jpg");
        $posts[] = $row;
    }

    echo json_encode($posts, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Erro ao buscar posts: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["erro" => "Não foi possível carregar as publicações."], JSON_UNESCAPED_UNICODE);
}