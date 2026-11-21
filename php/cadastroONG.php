<?php

session_start();

require_once 'paginaErro.php';
require_once "conexao.php";

// Recebe os dados do formulário
$nome = $_POST["nome"] ?? "";
$cnpj = $_POST["cnpj"] ?? "";
$email = $_POST["email"] ?? "";
$telefone = $_POST["telefone"] ?? "";
$senha = $_POST["senha"] ?? "";
$confirmarSenha = $_POST["confirmar-senha"] ?? "";

// Verifica se todos os campos foram preenchidos
if (
    empty($nome) ||
    empty($cnpj) ||
    empty($email) ||
    empty($telefone) ||
    empty($senha) ||
    empty($confirmarSenha)
) {
    exibirErro("Preencha todos os campos.");
}

// Verifica se as senhas são iguais
if ($senha !== $confirmarSenha) {
    exibirErro("As senhas não coincidem.");
}

// Remove caracteres do CNPJ
$cnpj = preg_replace('/\D/', '', $cnpj);

// Remove caracteres do telefone
$telefone = preg_replace('/\D/', '', $telefone);

// Criptografa a senha
$senhaCriptografada = password_hash($senha, PASSWORD_DEFAULT);

// Foto padrão
$fotoPerfil = "perfil.png";


// ================================
// 1. INSERE NA MoldeUsuario
// ================================

$sqlUsuario = "INSERT INTO MoldeUsuario
(nome, email, senha, fotoPerfil, telefone, nivelDeSeguranca)
VALUES (?, ?, ?, ?, ?, 2)";

$stmtUsuario = $conexao->prepare($sqlUsuario);

if (!$stmtUsuario) {
    exibirErro("Erro ao preparar cadastro do usuário: " . $conexao->error);
}

$stmtUsuario->bind_param(
    "sssss",
    $nome,
    $email,
    $senhaCriptografada,
    $fotoPerfil,
    $telefone
);

if (!$stmtUsuario->execute()) {
    exibirErro("Erro ao cadastrar usuário: " . $stmtUsuario->error);
}

// Pega o ID do usuário criado
$idMoldeUsuario = $conexao->insert_id;

$stmtUsuario->close();


// ================================
// 2. INSERE NA TABELA ONGs
// ================================

$sqlONG = "INSERT INTO ONGs
(idMoldeUsuario, CNPJ)
VALUES (?, ?)";

$stmtONG = $conexao->prepare($sqlONG);

if (!$stmtONG) {
    exibirErro("Erro ao preparar cadastro da ONG: " . $conexao->error);
}

$stmtONG->bind_param(
    "is",
    $idMoldeUsuario,
    $cnpj
);

if (!$stmtONG->execute()) {

    // Se o cadastro da ONG falhar,
    // remove o usuário criado anteriormente
    $stmtDelete = $conexao->prepare(
        "DELETE FROM MoldeUsuario WHERE idMUsuario = ?"
    );

    $stmtDelete->bind_param("i", $idMoldeUsuario);
    $stmtDelete->execute();
    $stmtDelete->close();

    exibirErro("Erro ao cadastrar ONG: " . $stmtONG->error);
}

$stmtONG->close();

$_SESSION["usuario_id"] = $idMoldeUsuario;
$_SESSION["usuario_nome"] = $nome;
$_SESSION["usuario_email"] = $email;
$_SESSION["usuario_foto"] = $fotoPerfil;

// Fecha a conexão
header("Location: inicialPage.php");
exit;

?>