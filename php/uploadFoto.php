<?php

session_start();

require_once "conexao.php";

header("Content-Type: application/json; charset=utf-8");


// =====================================
// VERIFICA USUÁRIO LOGADO
// =====================================

if (!isset($_SESSION["usuario_id"])) {
    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Usuário não está logado."
    ]);
    exit;
}

$idUsuario = $_SESSION["usuario_id"];


// =====================================
// VERIFICA SE RECEBEU UMA FOTO
// =====================================

if (!isset($_FILES["fotoPerfil"])) {
    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Nenhuma foto foi enviada."
    ]);
    exit;
}

$arquivo = $_FILES["fotoPerfil"];


// =====================================
// VERIFICA ERRO NO UPLOAD
// =====================================

if ($arquivo["error"] !== UPLOAD_ERR_OK) {
    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro ao enviar a foto."
    ]);
    exit;
}


// =====================================
// LIMITE DE TAMANHO
// 5 MB
// =====================================

if ($arquivo["size"] > 5 * 1024 * 1024) {
    echo json_encode([
        "sucesso" => false,
        "mensagem" => "A foto deve ter no máximo 5 MB."
    ]);
    exit;
}


// =====================================
// VERIFICA SE REALMENTE É UMA IMAGEM
// =====================================

$informacoesImagem = getimagesize($arquivo["tmp_name"]);

if ($informacoesImagem === false) {
    echo json_encode([
        "sucesso" => false,
        "mensagem" => "O arquivo enviado não é uma imagem válida."
    ]);
    exit;
}


// =====================================
// VERIFICA O TIPO DA IMAGEM
// =====================================

$tiposPermitidos = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp"
];

$tipo = $informacoesImagem["mime"];

if (!isset($tiposPermitidos[$tipo])) {
    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Formato de imagem não permitido."
    ]);
    exit;
}

$extensao = $tiposPermitidos[$tipo];


// =====================================
// PASTA DAS FOTOS
// =====================================

$pasta = __DIR__ . "/../uploadFoto/Perfil/";


// Cria a pasta caso ela não exista
if (!is_dir($pasta)) {
    mkdir($pasta, 0755, true);
}


// =====================================
// BUSCA A FOTO ANTIGA
// =====================================

$sql = "SELECT fotoPerfil
        FROM MoldeUsuario
        WHERE idMUsuario = ?";

$stmt = $conexao->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro ao consultar a foto atual."
    ]);
    exit;
}

$stmt->bind_param("i", $idUsuario);
$stmt->execute();

$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

$stmt->close();


// Guarda o caminho antigo
$fotoAntiga = $usuario["fotoPerfil"] ?? "";


// =====================================
// CRIA NOME ÚNICO PARA A NOVA FOTO
// =====================================

$nomeArquivo = bin2hex(random_bytes(16)) . "." . $extensao;

$caminhoFisico = $pasta . $nomeArquivo;

$caminhoBanco = "uploadFoto/Perfil/" . $nomeArquivo;


// =====================================
// MOVE A NOVA FOTO
// =====================================

if (!move_uploaded_file($arquivo["tmp_name"], $caminhoFisico)) {
    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Não foi possível salvar a foto."
    ]);
    exit;
}


// =====================================
// ATUALIZA O BANCO
// =====================================

$sql = "UPDATE MoldeUsuario
        SET fotoPerfil = ?
        WHERE idMUsuario = ?";

$stmt = $conexao->prepare($sql);

if (!$stmt) {

    // Apaga a nova foto caso o banco dê erro
    unlink($caminhoFisico);

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Erro ao preparar atualização da foto."
    ]);

    exit;
}

$stmt->bind_param("si", $caminhoBanco, $idUsuario);

if (!$stmt->execute()) {

    // Apaga a nova foto caso o banco dê erro
    unlink($caminhoFisico);

    $stmt->close();

    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Não foi possível atualizar a foto no banco."
    ]);

    exit;
}

$stmt->close();


// =====================================
// APAGA A FOTO ANTIGA
// =====================================

if (!empty($fotoAntiga)) {

    // Não tenta apagar a foto padrão
    if ($fotoAntiga !== "img/perfil/perfil.png") {

        $caminhoAntigo = __DIR__ . "/../" . $fotoAntiga;

        // Verifica se o arquivo realmente existe
        if (file_exists($caminhoAntigo)) {
            unlink($caminhoAntigo);
        }
    }
}


// =====================================
// RETORNA SUCESSO
// =====================================

echo json_encode([
    "sucesso" => true,
    "mensagem" => "Foto alterada com sucesso!",
    "foto" => $caminhoBanco
]);

?>