<?php

session_start();

require_once 'paginaErro.php';
require_once __DIR__ . "/conexao.php";

// Verifica se a variável de conexão foi realmente criada pelo arquivo incluído
if (!isset($conexao)) {
    exibirErro("Erro: conexão com o banco de dados não foi configurada.");
}

// Garante que o script só pode ser executado via requisição POST (ex: envio de formulário)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exibirErro("Acesso inválido.");
}


// trim() remove espaços em branco no início/fim das strings
// O operador ?? evita erro caso o campo não tenha sido enviado (assume string vazia)
$usuario = trim($_POST["usuario"] ?? "");
$dataNasc = $_POST["dataNasc"] ?? "";
$email = trim($_POST["email"] ?? "");
$telefone = trim($_POST["telefone"] ?? "");
$senha = $_POST["senha"] ?? "";
$confirmarSenha = $_POST["confirmar-senha"] ?? "";


// Verifica os campos

if (
    empty($usuario) ||
    empty($dataNasc) ||
    empty($email) ||
    empty($telefone) ||
    empty($senha) ||
    empty($confirmarSenha)
) {
    exibirErro("Preencha todos os campos.");
}

// Confere se a senha e a confirmação de senha são iguais
if ($senha !== $confirmarSenha) {
    exibirErro("As senhas não coincidem.");
}

// Verifica se o checkbox de aceite dos termos foi marcado
if (!isset($_POST["termos"])) {
    exibirErro("Você precisa aceitar os Termos de Uso.");
}


// Monta a consulta SQL usando placeholder (?) para evitar SQL Injection
$sql = "SELECT idMUsuario
        FROM MoldeUsuario
        WHERE email = ?";

// Prepara a consulta SQL (statement preparado)
$stmt = $conexao->prepare($sql);


// Verifica se a preparação da consulta deu certo
if (!$stmt) {
    exibirErro("Erro ao preparar consulta: " . $conexao->error);
}


// Associa o valor do e-mail ao parâmetro da consulta ("s" = tipo string)
$stmt->bind_param("s", $email);

// Executa a consulta no banco de dados
$stmt->execute();

// Obtém o resultado da consulta
$resultado = $stmt->get_result();


// Se encontrou alguma linha, significa que o e-mail já está cadastrado
if ($resultado->num_rows > 0) {
    exibirErro("Este e-mail já está cadastrado.");
}


// Fecha o statement após o uso
$stmt->close();

// Gera um hash seguro da senha usando o algoritmo padrão do PHP (bcrypt, por padrão)
$senhaCriptografada = password_hash(
    $senha,
    PASSWORD_DEFAULT
);

// Uma transação garante que as duas inserções (MoldeUsuario e UsuarioComum)
// só sejam confirmadas se ambas derem certo. Se uma falhar, tudo é desfeito (rollback).
$conexao->begin_transaction();

$fotoPadrao = "img/jpg/ftPerfil.jpg";

try {

    $sql = "INSERT INTO MoldeUsuario
            (nome, email, senha, fotoPerfil, telefone)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "Erro ao preparar MoldeUsuario: " . $conexao->error
        );
    }

    $stmt->bind_param(
        "sssss",
        $usuario,
        $email,
        $senhaCriptografada,
        $fotoPadrao,
        $telefone
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "Erro ao cadastrar usuário: " . $stmt->error
        );
    }

    $idMUsuario = $conexao->insert_id;
    $stmt->close();

    $sql = "INSERT INTO UsuarioComum
            (idMoldeUsuario, dataNasc)
            VALUES (?, ?)";

    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "Erro ao preparar UsuarioComum: " . $conexao->error
        );
    }

    $stmt->bind_param(
        "is",
        $idMUsuario,
        $dataNasc
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "Erro ao criar usuário comum: " . $stmt->error
        );
    }

    $stmt->close();
    $conexao->commit();

    $_SESSION["usuario_id"] = $idMUsuario;
    $_SESSION["usuario_nome"] = $usuario;
    $_SESSION["usuario_email"] = $email;
    $_SESSION["usuario_foto"] = $fotoPadrao;

} catch (Exception $e) {
    $conexao->rollback();
    exibirErro("Erro ao cadastrar: " . $e->getMessage());
}


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mantenha aqui as linhas em que você salva os dados na $_SESSION
// Exemplo: $_SESSION['id'] = $usuario['id'];

header("Location: inicialPage.php");
exit;
?>