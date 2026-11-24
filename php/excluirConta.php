<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION["usuario_id"])) {
    http_response_code(401);
    echo json_encode(["sucesso" => false, "erro" => "Usuário não autenticado."], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . "/conexao.php";

$dados = json_decode(file_get_contents("php://input"), true);
$senhaInformada = trim((string) ($dados["senha"] ?? $_POST["senha"] ?? ""));

if ($senhaInformada === "") {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "Informe sua senha atual para confirmar."], JSON_UNESCAPED_UNICODE);
    exit;
}

$idMUsuario = (int) $_SESSION["usuario_id"];

try {
    // 1. Busca dados e senha atual do usuário
    $stmtUser = $conexao->prepare("
        SELECT idEndereco, senha, fotoPerfil
        FROM MoldeUsuario
        WHERE idMUsuario = ?
        LIMIT 1
    ");
    $stmtUser->bind_param("i", $idMUsuario);
    $stmtUser->execute();
    $usuario = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(["sucesso" => false, "erro" => "Conta não encontrada."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $senhaBanco = (string) $usuario["senha"];
    $senhaValida = password_verify($senhaInformada, $senhaBanco) || hash_equals($senhaBanco, $senhaInformada);

    if (!$senhaValida) {
        http_response_code(403);
        echo json_encode(["sucesso" => false, "erro" => "Senha incorreta. A conta não foi excluída."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idEndereco = $usuario["idEndereco"] !== null ? (int) $usuario["idEndereco"] : null;
    $fotoPerfil = trim((string) ($usuario["fotoPerfil"] ?? ""));

    // 2. Inicia transação para remover todas as dependências sem erro de chave estrangeira
    $conexao->begin_transaction();

    // Curtidas feitas pelo usuário
    $stmt = $conexao->prepare("DELETE FROM curtidas WHERE idMUsuario = ?");
    $stmt->bind_param("i", $idMUsuario);
    $stmt->execute();
    $stmt->close();

    // Verifica se possui registro em UsuarioComum
    $stmtUc = $conexao->prepare("SELECT idUsuarioComum FROM UsuarioComum WHERE idMoldeUsuario = ? LIMIT 1");
    $stmtUc->bind_param("i", $idMUsuario);
    $stmtUc->execute();
    $uc = $stmtUc->get_result()->fetch_assoc();
    $stmtUc->close();

    if ($uc) {
        $idUsuarioComum = (int) $uc["idUsuarioComum"];

        foreach (["usuarioCausas", "comentarios", "favoritos", "doacoes"] as $tabelaUc) {
            $stmtDel = $conexao->prepare("DELETE FROM {$tabelaUc} WHERE idUsuarioComum = ?");
            $stmtDel->bind_param("i", $idUsuarioComum);
            $stmtDel->execute();
            $stmtDel->close();
        }

        $stmtDelUc = $conexao->prepare("DELETE FROM UsuarioComum WHERE idUsuarioComum = ?");
        $stmtDelUc->bind_param("i", $idUsuarioComum);
        $stmtDelUc->execute();
        $stmtDelUc->close();
    }

    // Verifica se possui registro em ONGs
    $stmtOng = $conexao->prepare("SELECT idONG FROM ONGs WHERE idMoldeUsuario = ? LIMIT 1");
    $stmtOng->bind_param("i", $idMUsuario);
    $stmtOng->execute();
    $ong = $stmtOng->get_result()->fetch_assoc();
    $stmtOng->close();

    if ($ong) {
        $idONG = (int) $ong["idONG"];

        // Atividades e favoritos ligados à ONG
        $stmt = $conexao->prepare("DELETE FROM atividades WHERE idONG = ?");
        $stmt->bind_param("i", $idONG);
        $stmt->execute();
        $stmt->close();

        $stmt = $conexao->prepare("DELETE FROM favoritos WHERE idONG = ?");
        $stmt->bind_param("i", $idONG);
        $stmt->execute();
        $stmt->close();

        // Comentários e curtidas nos posts da ONG
        $stmt = $conexao->prepare("
            DELETE c FROM comentarios c
            INNER JOIN posts p ON c.idPost = p.idPost
            WHERE p.idONG = ?
        ");
        $stmt->bind_param("i", $idONG);
        $stmt->execute();
        $stmt->close();

        $stmt = $conexao->prepare("
            DELETE cu FROM curtidas cu
            INNER JOIN posts p ON cu.idPost = p.idPost
            WHERE p.idONG = ?
        ");
        $stmt->bind_param("i", $idONG);
        $stmt->execute();
        $stmt->close();

        // Posts da ONG e posts vinculados às suas campanhas
        $stmt = $conexao->prepare("DELETE FROM posts WHERE idONG = ?");
        $stmt->bind_param("i", $idONG);
        $stmt->execute();
        $stmt->close();

        $stmt = $conexao->prepare("
            DELETE p FROM posts p
            INNER JOIN campanhas ca ON p.idCampanha = ca.idCampanha
            WHERE ca.idONG = ?
        ");
        $stmt->bind_param("i", $idONG);
        $stmt->execute();
        $stmt->close();

        // Causas e doações das campanhas da ONG
        $stmt = $conexao->prepare("
            DELETE cc FROM campanhaCausas cc
            INNER JOIN campanhas ca ON cc.idCampanha = ca.idCampanha
            WHERE ca.idONG = ?
        ");
        $stmt->bind_param("i", $idONG);
        $stmt->execute();
        $stmt->close();

        $stmt = $conexao->prepare("
            DELETE d FROM doacoes d
            INNER JOIN campanhas ca ON d.idCampanha = ca.idCampanha
            WHERE ca.idONG = ?
        ");
        $stmt->bind_param("i", $idONG);
        $stmt->execute();
        $stmt->close();

        // Campanhas e registro da ONG
        $stmt = $conexao->prepare("DELETE FROM campanhas WHERE idONG = ?");
        $stmt->bind_param("i", $idONG);
        $stmt->execute();
        $stmt->close();

        $stmt = $conexao->prepare("DELETE FROM ONGs WHERE idONG = ?");
        $stmt->bind_param("i", $idONG);
        $stmt->execute();
        $stmt->close();
    }

    // Remove o usuário base
    $stmt = $conexao->prepare("DELETE FROM MoldeUsuario WHERE idMUsuario = ?");
    $stmt->bind_param("i", $idMUsuario);
    $stmt->execute();
    $stmt->close();

    // Remove o endereço se não estiver sendo usado por outra conta
    if ($idEndereco !== null && $idEndereco > 0) {
        $stmtCheckEnd = $conexao->prepare("SELECT COUNT(*) AS total FROM MoldeUsuario WHERE idEndereco = ?");
        $stmtCheckEnd->bind_param("i", $idEndereco);
        $stmtCheckEnd->execute();
        $qtdEnd = (int) ($stmtCheckEnd->get_result()->fetch_assoc()["total"] ?? 0);
        $stmtCheckEnd->close();

        if ($qtdEnd === 0) {
            $stmtDelEnd = $conexao->prepare("DELETE FROM endereco WHERE idEndereco = ?");
            $stmtDelEnd->bind_param("i", $idEndereco);
            $stmtDelEnd->execute();
            $stmtDelEnd->close();
        }
    }

    $conexao->commit();

    // Remove foto de perfil personalizada do servidor, se existir
    if ($fotoPerfil !== "" && !str_contains($fotoPerfil, "ftPerfil.jpg") && !str_contains($fotoPerfil, "holder.jpg")) {
        $caminhoFoto = __DIR__ . "/../" . ltrim(str_replace("../", "", $fotoPerfil), "/");
        if (is_file($caminhoFoto)) {
            @unlink($caminhoFoto);
        }
    }

    // Encerra a sessão
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();

    echo json_encode(["sucesso" => true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if ($conexao->errno) {
        $conexao->rollback();
    }
    error_log("Erro ao excluir conta: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Não foi possível excluir a conta no momento."
    ], JSON_UNESCAPED_UNICODE);
}