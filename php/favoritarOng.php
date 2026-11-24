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
$idONG = (int) ($dados["idONG"] ?? $_POST["idONG"] ?? 0);
$idMUsuario = (int) $_SESSION["usuario_id"];

if ($idONG <= 0) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "erro" => "ONG inválida."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. Busca o idUsuarioComum correspondente ao usuário logado
    $stmtUc = $conexao->prepare("
        SELECT idUsuarioComum
        FROM UsuarioComum
        WHERE idMoldeUsuario = ?
        LIMIT 1
    ");
    $stmtUc->bind_param("i", $idMUsuario);
    $stmtUc->execute();
    $uc = $stmtUc->get_result()->fetch_assoc();
    $stmtUc->close();

    if (!$uc) {
        http_response_code(403);
        echo json_encode([
            "sucesso" => false,
            "erro" => "Apenas contas de usuário comum podem favoritar ONGs."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idUsuarioComum = (int) $uc["idUsuarioComum"];

    // 2. Verifica se a ONG existe
    $stmtOng = $conexao->prepare("SELECT idONG FROM ONGs WHERE idONG = ? LIMIT 1");
    $stmtOng->bind_param("i", $idONG);
    $stmtOng->execute();
    $ongExiste = $stmtOng->get_result()->num_rows > 0;
    $stmtOng->close();

    if (!$ongExiste) {
        http_response_code(404);
        echo json_encode(["sucesso" => false, "erro" => "ONG não encontrada."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. Alterna entre favoritar e remover dos favoritos
    $stmtCheck = $conexao->prepare("
        SELECT idFavorito
        FROM favoritos
        WHERE idUsuarioComum = ? AND idONG = ?
        LIMIT 1
    ");
    $stmtCheck->bind_param("ii", $idUsuarioComum, $idONG);
    $stmtCheck->execute();
    $jaFavoritado = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    if ($jaFavoritado) {
        $stmtDel = $conexao->prepare("DELETE FROM favoritos WHERE idUsuarioComum = ? AND idONG = ?");
        $stmtDel->bind_param("ii", $idUsuarioComum, $idONG);
        $stmtDel->execute();
        $stmtDel->close();
        $favoritado = false;
    } else {
        $stmtIns = $conexao->prepare("INSERT INTO favoritos (idUsuarioComum, idONG) VALUES (?, ?)");
        $stmtIns->bind_param("ii", $idUsuarioComum, $idONG);
        $stmtIns->execute();
        $stmtIns->close();
        $favoritado = true;
    }

    // 4. Retorna o total atualizado de ONGs favoritas do usuário
    $stmtTotal = $conexao->prepare("SELECT COUNT(*) AS total FROM favoritos WHERE idUsuarioComum = ?");
    $stmtTotal->bind_param("i", $idUsuarioComum);
    $stmtTotal->execute();
    $totalFavoritos = (int) ($stmtTotal->get_result()->fetch_assoc()["total"] ?? 0);
    $stmtTotal->close();

    echo json_encode([
        "sucesso" => true,
        "favoritado" => $favoritado,
        "totalFavoritos" => $totalFavoritos
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log("Erro em favoritarOng.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "sucesso" => false,
        "erro" => "Não foi possível atualizar os favoritos."
    ], JSON_UNESCAPED_UNICODE);
}