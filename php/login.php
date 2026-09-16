<?php

// Inicia (ou retoma) a sessão do usuário, permitindo guardar dados que persistem entre as páginas (ex: usuário logado)
session_start();

// Inclui o arquivo responsável por criar a conexão com o banco de dados
require_once "conexao.php";

// Garante que o script só pode ser executado via requisição POST (ex: envio de formulário)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Interrompe o script se alguém tentar acessar a página diretamente
    // (ex: digitando a URL no navegador) em vez de enviar o formulário
    die("Acesso inválido.");
}

// O operador ?? evita erro caso o campo não tenha sido enviado (assume string vazia)
$login = $_POST["login"] ?? "";
$senha = $_POST["senha"] ?? "";

// Verifica se os campos foram preenchidos
if (empty($login) || empty($senha)) {
    // Interrompe o script se o usuário deixou login ou senha em branco
    die("Preencha todos os campos.");
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
    // Interrompe o script se houve erro ao preparar a query
    // (ex: erro de sintaxe SQL ou problema de conexão)
    die("Erro ao preparar consulta: " . $conexao->error);
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
    // Interrompe o script se nenhum usuário foi encontrado com esse e-mail.
    // A mensagem é genérica ("E-mail ou senha incorretos") de propósito,
    // para não revelar a quem tenta invadir se o e-mail existe ou não no sistema
    die("E-mail ou senha incorretos.");
}


// =====================================
// VERIFICA A SENHA
// =====================================

if (!password_verify($senha, $usuario["senha"])) {
    // password_verify() compara a senha digitada com o hash salvo no banco
    // (o mesmo hash gerado por password_hash() no cadastro).
    // Se não bater, interrompe o script com a mesma mensagem genérica de antes
    die("E-mail ou senha incorretos.");
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
$_SESSION["usuario_foto"] = $usuario["fotoPerfil"];


// =====================================
// REDIRECIONA
// =====================================
// Após o login bem-sucedido, redireciona o usuário para a página inicial
header("Location: ../php/inicialPage.php");
exit;

?>