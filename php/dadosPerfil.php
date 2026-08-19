<?php

session_start();

require_once "conexao.php";

header("Content-Type: application/json; charset=UTF-8");


// Verifica login
if (!isset($_SESSION["usuario_id"])) {

    http_response_code(401);

    echo json_encode([
        "erro" => "Usuário não está logado."
    ]);

    exit;
}


$idUsuario = $_SESSION["usuario_id"];


// Busca usuário
$sql = "SELECT nome, email, telefone, fotoPerfil
        FROM MoldeUsuario
        WHERE idMUsuario = ?";

$stmt = $conexao->prepare($sql);

$stmt->bind_param("i", $idUsuario);

$stmt->execute();

$resultado = $stmt->get_result();

$usuario = $resultado->fetch_assoc();

$stmt->close();

$conexao->close();


if (!$usuario) {

    http_response_code(404);

    echo json_encode([
        "erro" => "Usuário não encontrado."
    ]);

    exit;
}


// Valores padrão
$usuario["nome"] = $usuario["nome"] ?: "-";
$usuario["email"] = $usuario["email"] ?: "-";
$usuario["telefone"] = $usuario["telefone"] ?: "-";
$usuario["fotoPerfil"] = $usuario["fotoPerfil"] ?: "perfil.png";


echo json_encode($usuario);

?>