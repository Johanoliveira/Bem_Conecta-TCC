<?php

// Inclui o arquivo responsável por criar a conexão com o banco de dados
require_once __DIR__ . "/conexao.php";

// Verifica se a variável de conexão foi realmente criada pelo arquivo incluído
if (!isset($conexao)) {
    // die() imprime a mensagem na tela e encerra o script imediatamente,
    // ou seja, nenhuma linha abaixo desta será executada
    die("Erro: conexão com o banco de dados não foi configurada.");
}

// Garante que o script só pode ser executado via requisição POST (ex: envio de formulário)
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // Interrompe o script se alguém tentar acessar a página diretamente
    // (ex: digitando a URL no navegador) em vez de enviar o formulário
    die("Acesso inválido.");
}


// trim() remove espaços em branco no início/fim das strings
// O operador ?? evita erro caso o campo não tenha sido enviado (assume string vazia)
$usuario = trim($_POST["usuario"] ?? "");
$dataNasc = $_POST["dataNasc"] ?? "";
$email = trim($_POST["email"] ?? "");
$telefone = trim($_POST["telefone"] ?? "");
$senha = $_POST["senha"] ?? "";
$confirmarSenha = $_POST["confirmar-senha"] ?? "";


// =====================================
// VALIDAÇÃO DOS CAMPOS OBRIGATÓRIOS
// =====================================
// Verifica se algum dos campos essenciais está vazio
if (empty($usuario) || empty($dataNasc) || empty($email) || empty($telefone) || empty($senha) || empty($confirmarSenha)) {
    // Interrompe o script se o usuário deixou algum campo em branco
    die("Preencha todos os campos.");
}

// Confere se a senha e a confirmação de senha são iguais
if ($senha !== $confirmarSenha) {
    // Interrompe o script se a senha e a confirmação forem diferentes
    die("As senhas não coincidem.");
}

// Verifica se o checkbox de aceite dos termos foi marcado
if (!isset($_POST["termos"])) {
    // Interrompe o script se o checkbox de termos não foi marcado
    die("Você precisa aceitar os Termos de Uso.");
}


// Monta a consulta SQL usando placeholder (?) para evitar SQL Injection
$sql = "SELECT idMUsuario
        FROM MoldeUsuario
        WHERE email = ?";

// Prepara a consulta SQL (statement preparado)
$stmt = $conexao->prepare($sql);


// Verifica se a preparação da consulta deu certo
if (!$stmt) {
    // Interrompe o script se houve algum erro ao preparar a consulta SQL
    // (ex: erro de sintaxe na query ou problema de conexão)
    die("Erro ao preparar consulta: " . $conexao->error);
}


// Associa o valor do e-mail ao parâmetro da consulta ("s" = tipo string)
$stmt->bind_param("s", $email);

// Executa a consulta no banco de dados
$stmt->execute();

// Obtém o resultado da consulta
$resultado = $stmt->get_result();


// Se encontrou alguma linha, significa que o e-mail já está cadastrado
if ($resultado->num_rows > 0) {
    // Interrompe o script se já existir um usuário com esse e-mail no banco
    die("Este e-mail já está cadastrado.");
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


// O bloco try é onde colocamos o código "arriscado" que queremos monitorar.
// Se qualquer erro (Exception) for lançado dentro dele, o PHP interrompe
// a execução do try imediatamente e pula direto para o bloco catch.
try {

    $sql = "INSERT INTO MoldeUsuario
            (nome, email, senha, telefone)
            VALUES (?, ?, ?, ?)";

    // Prepara a consulta de inserção
    $stmt = $conexao->prepare($sql);

    // Verifica se a preparação deu certo
    if (!$stmt) {
        // throw new Exception(...) "lança" um erro manualmente.
        // Isso cria um objeto de exceção com a mensagem informada e
        // interrompe o try na hora, transferindo o controle para o catch
        // (as linhas abaixo deste throw, dentro do try, não são executadas)
        throw new Exception(
            "Erro ao preparar MoldeUsuario: " . $conexao->error
        );
    }

    // Associa os valores aos parâmetros da query ("ssss" = 4 parâmetros do tipo string)
    $stmt->bind_param(
        "ssss",
        $usuario,
        $email,
        $senhaCriptografada,
        $telefone
    );

    // Executa a inserção e verifica se houve erro
    if (!$stmt->execute()) {
        // Lança uma exceção se a inserção falhar, interrompendo o try
        // e enviando o controle para o catch logo abaixo
        throw new Exception(
            "Erro ao cadastrar usuário: " . $stmt->error
        );
    }

    // Recupera o ID gerado automaticamente pela inserção (chave primária do novo usuário)
    $idMUsuario = $conexao->insert_id;

    // Fecha o statement após o uso
    $stmt->close();

    // Essa tabela guarda dados específicos do "usuário comum", vinculados ao usuário base criado acima através do idMoldeUsuario
    $sql = "INSERT INTO UsuarioComum
            (idMoldeUsuario, dataNasc)
            VALUES (?, ?)";

    // Prepara a consulta de inserção
    $stmt = $conexao->prepare($sql);

    // Verifica se a preparação deu certo
    if (!$stmt) {
        // Mesma lógica: se der erro ao preparar a consulta, lança a exceção e para a execução do try imediatamente
        throw new Exception(
            "Erro ao preparar UsuarioComum: " . $conexao->error
        );
    }

    // Associa os valores aos parâmetros ("i" = inteiro, "s" = string)
    $stmt->bind_param(
        "is",
        $idMUsuario,
        $dataNasc
    );

    // Executa a inserção e verifica se houve erro
    if (!$stmt->execute()) {
        // Lança a exceção se essa segunda inserção falhar
        throw new Exception(
            "Erro ao criar usuário comum: " . $stmt->error
        );
    }

    // Fecha o statement após o uso
    $stmt->close();

    // Se chegou até aqui sem erros, confirma definitivamente as duas inserções no banco
    $conexao->commit();

    // Mensagem de sucesso
    echo "Cadastro realizado com sucesso!";


// O bloco catch "captura" a exceção lançada por qualquer throw dentro do try.
// $e é o objeto da exceção capturada — ele guarda a mensagem de erro que
// foi passada no throw new Exception("..."), acessível através de $e->getMessage().
// O código aqui dentro só roda se algum throw tiver acontecido lá em cima.
} catch (Exception $e) {

    // Se qualquer uma das inserções falhar, desfaz tudo que foi feito na transação
    $conexao->rollback();

    // Exibe a mensagem de erro capturada
    echo "Erro ao cadastrar: " . $e->getMessage();
}

// Fecha a conexão com o banco de dados
$conexao->close();

// Após o processamento, redireciona o usuário para a página inicial
header("Location: ../php/inicialPage.php");
exit;
?>