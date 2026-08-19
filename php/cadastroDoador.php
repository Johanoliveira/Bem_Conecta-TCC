<?php

require_once __DIR__ . "/conexao.php";

if (!isset($conexao)) {
    die("Erro: conexão com o banco de dados não foi configurada.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Acesso inválido.");
}


// Recebe os dados do formulário

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
    die("Preencha todos os campos.");
}


// Verifica as senhas

if ($senha !== $confirmarSenha) {
    die("As senhas não coincidem.");
}


// Verifica os termos

if (!isset($_POST["termos"])) {
    die("Você precisa aceitar os Termos de Uso.");
}


// Verifica se o e-mail já existe

$sql = "SELECT idMUsuario
        FROM MoldeUsuario
        WHERE email = ?";

$stmt = $conexao->prepare($sql);

if (!$stmt) {
    die("Erro ao preparar consulta: " . $conexao->error);
}

$stmt->bind_param("s", $email);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    die("Este e-mail já está cadastrado.");
}

$stmt->close();


// Criptografa a senha

$senhaCriptografada = password_hash(
    $senha,
    PASSWORD_DEFAULT
);


// Inicia a transação

$conexao->begin_transaction();

try {

    // Cria o usuário base

    $sql = "INSERT INTO MoldeUsuario
            (nome, email, senha, telefone)
            VALUES (?, ?, ?, ?)";

    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "Erro ao preparar MoldeUsuario: " . $conexao->error
        );
    }

    $stmt->bind_param(
        "ssss",
        $usuario,
        $email,
        $senhaCriptografada,
        $telefone
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "Erro ao cadastrar usuário: " . $stmt->error
        );
    }

    $idMUsuario = $conexao->insert_id;

    $stmt->close();


    // Cria o usuário comum

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


    // Confirma as alterações

    $conexao->commit();

    echo "Cadastro realizado com sucesso!";


} catch (Exception $e) {

    $conexao->rollback();

    echo "Erro ao cadastrar: " . $e->getMessage();
}


$conexao->close();

?>