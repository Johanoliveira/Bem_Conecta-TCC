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
$idCampanha = (int) ($dados["idCampanha"] ?? $_POST["idCampanha"] ?? 0);
$usuarioId = (int) $_SESSION["usuario_id"];

if ($idCampanha <= 0) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "Campanha inválida."]);
    exit;
}

try {
    // 1. Verifica nível de segurança e ONG do usuário logado
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
        echo json_encode(["sucesso" => false, "erro" => "Você não tem permissão para excluir campanhas."]);
        exit;
    }

    // 2. Verifica se a campanha existe e pertence à ONG (ou libera se for Admin nível 3)
    if ($nivel === 2) {
        if ($idOngUsuario === null) {
            http_response_code(403);
            echo json_encode(["sucesso" => false, "erro" => "Sua conta não está vinculada a uma ONG."]);
            exit;
        }

        $stmtCamp = $conexao->prepare("SELECT idCampanha FROM campanhas WHERE idCampanha = ? AND idONG = ? LIMIT 1");
        $stmtCamp->bind_param("ii", $idCampanha, $idOngUsuario);
    } else {
        $stmtCamp = $conexao->prepare("SELECT idCampanha FROM campanhas WHERE idCampanha = ? LIMIT 1");
        $stmtCamp->bind_param("i", $idCampanha);
    }

    $stmtCamp->execute();
    $campanha = $stmtCamp->get_result()->fetch_assoc();
    $stmtCamp->close();

    if (!$campanha) {
        http_response_code(403);
        echo json_encode(["sucesso" => false, "erro" => "Campanha não encontrada ou sem permissão para excluir."]);
        exit;
    }

    // 3. Busca imagens dos posts vinculados à campanha para apagar do servidor depois
    $imagensParaApagar = [];
    $stmtImgs = $conexao->prepare("SELECT conteudo FROM posts WHERE idCampanha = ?");
    $stmtImgs->bind_param("i", $idCampanha);
    $stmtImgs->execute();
    $resImgs = $stmtImgs->get_result();
    while ($row = $resImgs->fetch_assoc()) {
        if (!empty($row["conteudo"])) {
            $imagensParaApagar[] = $row["conteudo"];
        }
    }
    $stmtImgs->close();

    // 4. Exclui interações, posts associados, vínculos e a campanha em transação
    $conexao->begin_transaction();

    // Curtidas dos posts da campanha
    $stmtCurtidas = $conexao->prepare("
        DELETE FROM curtidas
        WHERE idPost IN (SELECT idPost FROM posts WHERE idCampanha = ?)
    ");
    $stmtCurtidas->bind_param("i", $idCampanha);
    $stmtCurtidas->execute();
    $stmtCurtidas->close();

    // Comentários dos posts da campanha
    $stmtComentarios = $conexao->prepare("
        DELETE FROM comentarios
        WHERE idPost IN (SELECT idPost FROM posts WHERE idCampanha = ?)
    ");
    $stmtComentarios->bind_param("i", $idCampanha);
    $stmtComentarios->execute();
    $stmtComentarios->close();

    // Posts vinculados à campanha
    $stmtPosts = $conexao->prepare("DELETE FROM posts WHERE idCampanha = ?");
    $stmtPosts->bind_param("i", $idCampanha);
    $stmtPosts->execute();
    $stmtPosts->close();

    // Causas da campanha
    $stmtCausas = $conexao->prepare("DELETE FROM campanhaCausas WHERE idCampanha = ?");
    $stmtCausas->bind_param("i", $idCampanha);
    $stmtCausas->execute();
    $stmtCausas->close();

    // Doações vinculadas à campanha (para não travar na chave estrangeira)
    $stmtDoacoes = $conexao->prepare("DELETE FROM doacoes WHERE idCampanha = ?");
    $stmtDoacoes->bind_param("i", $idCampanha);
    $stmtDoacoes->execute();
    $stmtDoacoes->close();

    // Por fim, exclui a própria campanha
    $stmtDeleteCamp = $conexao->prepare("DELETE FROM campanhas WHERE idCampanha = ?");
    $stmtDeleteCamp->bind_param("i", $idCampanha);
    $stmtDeleteCamp->execute();
    $stmtDeleteCamp->close();

    $conexao->commit();

    // 5. Remove arquivos físicos das imagens dos posts excluídos
    foreach ($imagensParaApagar as $caminhoImagem) {
        if (strpos($caminhoImagem, "../img/posts/") === 0) {
            $arquivoFisico = __DIR__ . "/../img/posts/" . basename($caminhoImagem);
            if (is_file($arquivoFisico)) {
                unlink($arquivoFisico);
            }
        }
    }

    echo json_encode(["sucesso" => true]);

} catch (Throwable $e) {
    if ($conexao->errno) {
        $conexao->rollback();
    }
    http_response_code(500);
    echo json_encode(["sucesso" => false, "erro" => "Erro ao excluir campanha: " . $e->getMessage()]);
}