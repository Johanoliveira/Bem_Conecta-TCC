<?php

session_start();

require_once 'paginaErro.php';
require_once "conexao.php";

// Verifica se os dados foram enviados
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exibirErro("Acesso inválido.");
}

// Recebe os dados do formulário
$login = $_POST["login"] ?? "";
$senha = $_POST["senha"] ?? "";

// Verifica se os campos foram preenchidos
if (empty($login) || empty($senha)) {
    exibirErro("Preencha todos os campos.");
}


// =====================================
// PROCURA O USUÁRIO PELO E-MAIL
// =====================================

$sql = "SELECT idMUsuario, nome, email, senha, fotoPerfil
        FROM MoldeUsuario
        WHERE email = ?";

$stmt = $conexao->prepare($sql);

if (!$stmt) {
    exibirErro("Erro ao preparar consulta: " . $conexao->error);
}

$stmt->bind_param("s", $login);
$stmt->execute();

$resultado = $stmt->get_result();

$usuario = $resultado->fetch_assoc();

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

$_SESSION["usuario_id"] = $usuario["idMUsuario"];
$_SESSION["usuario_nome"] = $usuario["nome"];
$_SESSION["usuario_email"] = $usuario["email"];
$_SESSION["usuario_foto"] = $usuario["fotoPerfil"];


// =====================================
// REDIRECIONA
// =====================================

// Exemplo quando o usuário errar login ou senha:
$mensagem = "E-mail ou senha incorretos. Verifique seus dados e tente novamente.";
$linkVoltar = "../html/login.html";
$textoBotao = "Voltar para o Login";
include 'paginaErro.php';
exit;

?>