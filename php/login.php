<?php

session_start();

// Dados de conexão (ajuste conforme seu ambiente)
$host = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "BemConecta";

// Conecta ao banco de dados
$conexao = mysqli_connect($host, $dbuser, $dbpass, $dbname);

if (!$conexao) {
    die("Erro ao conectar: " . mysqli_connect_error());
}

// Pega os dados enviados pelo formulário
$emailOuUsuario = $_POST["email"];
$senha = $_POST["senha"];

// Procura primeiro na tabela de doadores
$sql = "SELECT * FROM doadores WHERE email = '$emailOuUsuario' OR usuario = '$emailOuUsuario'";
$resultado = mysqli_query($conexao, $sql);
$dados = mysqli_fetch_assoc($resultado);

// Se não achou, procura na tabela de ONGs
if (!$dados) {
    $sql = "SELECT * FROM ongs WHERE email = '$emailOuUsuario'";
    $resultado = mysqli_query($conexao, $sql);
    $dados = mysqli_fetch_assoc($resultado);
}

// Verifica se encontrou o usuário e se a senha está correta
if ($dados && password_verify($senha, $dados["senha"])) {
    $_SESSION["usuario_id"] = $dados["id"];
    header("Location: ../html/dashboard.html");
    exit;
} else {
    echo "Email/usuário ou senha incorretos.";
}

//fecha a conexão com o banco de dados
mysqli_close($conexao);
