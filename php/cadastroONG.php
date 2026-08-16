<?php

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
$nome = $_POST["nome"];
$cnpj = $_POST["cnpj"];
$email = $_POST["email"];
$telefone = $_POST["telefone"];
$senha = $_POST["senha"];
$confirmarSenha = $_POST["confirmar-senha"];

// Verifica se as senhas são iguais
if ($senha != $confirmarSenha) {
    die("As senhas não coincidem.");
}

// Criptografa a senha
$senhaCriptografada = password_hash($senha, PASSWORD_DEFAULT);

// Monta e executa o comando de inserção
$sql = "INSERT INTO ongs (nome, cnpj, email, telefone, senha)
        VALUES ('$nome', '$cnpj', '$email', '$telefone', '$senhaCriptografada')";

if (mysqli_query($conexao, $sql)) {
    echo "Cadastro realizado com sucesso!";
} else {
    echo "Erro ao cadastrar: " . mysqli_error($conexao);
}

//fecha a conexão com o banco de dados
mysqli_close($conexao);