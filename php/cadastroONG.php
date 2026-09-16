<?php

// Inclui o arquivo responsável por criar a conexão com o banco de dados
require_once "conexao.php";

// =====================================
// RECEBE OS DADOS DO FORMULÁRIO
// =====================================
// O operador ?? evita erro caso o campo não tenha sido enviado (assume string vazia)
$nome = $_POST["nome"] ?? "";
$cnpj = $_POST["cnpj"] ?? "";
$descricao = trim($_POST["descricao"] ?? "");
$email = $_POST["email"] ?? "";
$telefone = $_POST["telefone"] ?? "";
$senha = $_POST["senha"] ?? "";
$confirmarSenha = $_POST["confirmar-senha"] ?? "";
$fotoPerfil = null;


// Verifica se algum dos campos essenciais está vazio
if (empty($nome) || empty($cnpj) || empty($descricao) || empty($email) || empty($telefone) || empty($senha) || empty($confirmarSenha)) {
    // Interrompe o script se o usuário deixou algum campo em branco
    die("Preencha todos os campos.");
}

// Confere se a senha e a confirmação de senha são iguais
if ($senha !== $confirmarSenha) {
    // Interrompe o script se a senha e a confirmação forem diferentes
    die("As senhas não coincidem.");
}

// Remove tudo que não for dígito do CNPJ (pontos, barra e traço), deixando só os números
$cnpj = preg_replace('/\D/', '', $cnpj);

// Remove tudo que não for dígito do telefone (parênteses, espaço e traço), deixando só os números
$telefone = preg_replace('/\D/', '', $telefone);

// Gera um hash seguro da senha usando o algoritmo padrão do PHP (bcrypt, por padrão)
$senhaCriptografada = password_hash($senha, PASSWORD_DEFAULT);


// ================================
// 1. INSERE NA MoldeUsuario
// ================================
// Cria o registro base do usuário (dados comuns a qualquer tipo de conta)

$sqlUsuario = "INSERT INTO MoldeUsuario
(nome, email, senha, fotoPerfil, telefone, nivelDeSeguranca)
VALUES (?, ?, ?, ?, ?, 2)";

// Prepara a consulta SQL usando placeholders (?) para evitar SQL Injection
$stmtUsuario = $conexao->prepare($sqlUsuario);

// Verifica se a preparação da consulta deu certo
if (!$stmtUsuario) {
    // Interrompe o script se houve erro ao preparar a query
    // (ex: erro de sintaxe SQL ou problema de conexão)
    die("Erro ao preparar cadastro do usuário: " . $conexao->error);
}

// Associa os valores aos parâmetros da query ("sssss" = 5 parâmetros do tipo string)
$stmtUsuario->bind_param("sssss", $nome, $email, $senhaCriptografada, $fotoPerfil, $telefone);

// Executa a inserção; se falhar, interrompe o script
if (!$stmtUsuario->execute()) {
    die("Erro ao cadastrar usuário: " . $stmtUsuario->error);
}

// Pega o ID gerado automaticamente pela inserção (chave primária do novo usuário)
$idMoldeUsuario = $conexao->insert_id;

// Fecha o statement após o uso
$stmtUsuario->close();


// ================================
// 2. INSERE NA TABELA ONGs
// ================================
// Cria o registro específico da ONG, vinculado ao usuário base criado acima

$sqlONG = "INSERT INTO ONGs
(idMoldeUsuario, descricao, CNPJ)
VALUES (?, ?, ?)";

// Prepara a consulta de inserção
$stmtONG = $conexao->prepare($sqlONG);

// Verifica se a preparação da consulta deu certo
if (!$stmtONG) {
    die("Erro ao preparar cadastro da ONG: " . $conexao->error);
}

// Associa os valores aos parâmetros ("i" = inteiro, "s" = string)
$stmtONG->bind_param(
    "iss",
    $idMoldeUsuario,
    $descricao,
    $cnpj
);

// Executa a inserção da ONG
if (!$stmtONG->execute()) {

    // Se o cadastro da ONG falhar, é preciso desfazer manualmente o que já foi
    // feito, já que este script não usa transação (begin_transaction/rollback)
    // como no cadastro de usuário comum. Por isso, remove o usuário criado
    // anteriormente para não deixar um registro "órfão" (usuário sem ONG vinculada)
    $stmtDelete = $conexao->prepare(
        "DELETE FROM MoldeUsuario WHERE idMUsuario = ?"
    );

    // Associa o ID do usuário criado ao parâmetro da consulta de exclusão
    $stmtDelete->bind_param("i", $idMoldeUsuario);

    // Executa a exclusão
    $stmtDelete->execute();

    // Fecha o statement de exclusão
    $stmtDelete->close();

    // Interrompe o script informando o erro no cadastro da ONG
    die("Erro ao cadastrar ONG: " . $stmtONG->error);
}

// Fecha o statement após o uso
$stmtONG->close();


// Fecha a conexão com o banco de dados
$conexao->close();

// Após o processamento, redireciona o usuário para a página inicial
header("Location: ../php/inicialPage.php");
exit;

?>