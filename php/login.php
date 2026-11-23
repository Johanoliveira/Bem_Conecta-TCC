<?php

// Inicia (ou retoma) a sessão do usuário, permitindo guardar dados que persistem entre as páginas (ex: usuário logado)
session_start();

require_once 'paginaErro.php';
require_once "conexao.php";

// Garante que o script só pode ser executado via requisição POST (ex: envio de formulário)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exibirErro("Acesso inválido.");
}

// O operador ?? evita erro caso o campo não tenha sido enviado (assume string vazia)
$login = $_POST["login"] ?? "";
$senha = $_POST["senha"] ?? "";

// Verifica se os campos foram preenchidos
if (empty($login) || empty($senha)) {
    exibirErro("Preencha todos os campos.");
}


// =====================================
// PROCURA O USUÁRIO PELO E-MAIL
// =====================================
// Aqui $login é tratado como o e-mail digitado pelo usuário

$sql = "SELECT idMUsuario, nome, email, senha, fotoPerfil
        FROM MoldeUsuario
        WHERE email = ?";

// Prepara a consulta SQL usando placeholder (?) para evitar SQL Injection
$stmt = $conexao->prepare($sql);

// Verifica se a preparação da consulta deu certo
if (!$stmt) {
    exibirErro("Erro ao preparar consulta: " . $conexao->error);
}

// Associa o valor do e-mail digitado ao parâmetro da consulta ("s" = tipo string)
$stmt->bind_param("s", $login);

// Executa a consulta no banco de dados
$stmt->execute();

// Obtém o resultado da consulta
$resultado = $stmt->get_result();

// Pega a primeira linha do resultado como um array associativo
// (ex: $usuario["nome"], $usuario["email"], etc.)
// Se não encontrar nenhum usuário com esse e-mail, $usuario será null
$usuario = $resultado->fetch_assoc();

// Fecha o statement após o uso
$stmt->close();


// =====================================
// VERIFICA SE O USUÁRIO EXISTE
// =====================================

if (!$usuario) {
    exibirErro("E-mail ou senha incorretos.");
}


// =====================================
// VERIFICA A SENHA
// =====================================

if (!password_verify($senha, $usuario["senha"])) {
    exibirErro("E-mail ou senha incorretos.");
}


// =====================================
// CRIA A SESSÃO
// =====================================
// Se chegou até aqui, login e senha estão corretos.
// Os dados do usuário são salvos na sessão ($_SESSION) para que outras
// páginas do site saibam que ele está logado e possam identificá-lo

$_SESSION["usuario_id"] = $usuario["idMUsuario"];
$_SESSION["usuario_nome"] = $usuario["nome"];
$_SESSION["usuario_email"] = $usuario["email"];
$_SESSION["usuario_foto"] = !empty($usuario["fotoPerfil"])
    ? $usuario["fotoPerfil"]
    : "img/jpg/ftPerfil.jpg";
// =====================================
// REDIRECIONA
// =====================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mantenha aqui as linhas em que você salva os dados na $_SESSION
// Exemplo: $_SESSION['id'] = $usuario['id'];

header("Location: inicialPage.php");
exit;

?>