<?php

$host = "localhost";
$usuario = "root";
$senha = "123456789";
$banco = "banco";

$conexao = new mysqli($host, $usuario, $senha, $banco);

if ($conexao->connect_error) {
    die("Erro ao conectar ao banco de dados: " . $conexao->connect_error);
}

$conexao->set_charset("utf8mb4");

?>