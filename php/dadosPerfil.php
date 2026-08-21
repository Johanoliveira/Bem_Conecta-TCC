<?php

session_start();

require_once "conexao.php";

header("Content-Type: application/json; charset=UTF-8");


// =========================================
// VERIFICA SE ESTÁ LOGADO
// =========================================

if (!isset($_SESSION["usuario_id"])) {

    http_response_code(401);

    echo json_encode([
        "erro" => "Usuário não está logado."
    ]);

    exit;
}


$idUsuario = $_SESSION["usuario_id"];


// =========================================
// DADOS PRINCIPAIS DO USUÁRIO
// =========================================

$sql = "
    SELECT
        m.idMUsuario,
        m.nome,
        m.email,
        m.telefone,
        m.fotoPerfil,
        m.dataCriacao,

        e.cidade,
        e.estado

    FROM MoldeUsuario m

    LEFT JOIN endereco e
        ON m.idEndereco = e.idEndereco

    WHERE m.idMUsuario = ?
";


$stmt = $conexao->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "erro" => "Erro ao preparar consulta."
    ]);

    exit;
}


$stmt->bind_param("i", $idUsuario);

$stmt->execute();

$resultado = $stmt->get_result();

$usuario = $resultado->fetch_assoc();

$stmt->close();


if (!$usuario) {

    http_response_code(404);

    echo json_encode([
        "erro" => "Usuário não encontrado."
    ]);

    exit;
}


// =========================================
// VERIFICA SE É USUÁRIO COMUM
// =========================================

$sqlComum = "
    SELECT idUsuarioComum
    FROM UsuarioComum
    WHERE idMoldeUsuario = ?
";


$stmt = $conexao->prepare($sqlComum);

$stmt->bind_param("i", $idUsuario);

$stmt->execute();

$resultado = $stmt->get_result();

$usuarioComum = $resultado->fetch_assoc();

$stmt->close();


// Se não for usuário comum
if (!$usuarioComum) {

    echo json_encode([
        "nome" => $usuario["nome"] ?: "-",
        "email" => $usuario["email"] ?: "-",
        "telefone" => $usuario["telefone"] ?: "-",
        "foto" => $usuario["fotoPerfil"] ?: "perfil.png",
        "dataCriacao" => $usuario["dataCriacao"] ?: "-",
        "cidade" => $usuario["cidade"] ?: "-",
        "estado" => $usuario["estado"] ?: "-",

        "totalDoado" => "-",
        "campanhasApoiadas" => "-",
        "ongsFavoritas" => "-",
        "causas" => []
    ]);

    exit;
}


$idUsuarioComum = $usuarioComum["idUsuarioComum"];


// =========================================
// TOTAL DOADO
// =========================================

$sqlDoado = "
    SELECT COALESCE(SUM(valor), 0) AS total
    FROM doacoes
    WHERE idUsuarioComum = ?
";


$stmt = $conexao->prepare($sqlDoado);

$stmt->bind_param("i", $idUsuarioComum);

$stmt->execute();

$resultado = $stmt->get_result();

$doado = $resultado->fetch_assoc();

$stmt->close();


// =========================================
// CAMPANHAS APOIADAS
// =========================================

$sqlCampanhas = "
    SELECT COUNT(DISTINCT idCampanha) AS total
    FROM doacoes
    WHERE idUsuarioComum = ?
";


$stmt = $conexao->prepare($sqlCampanhas);

$stmt->bind_param("i", $idUsuarioComum);

$stmt->execute();

$resultado = $stmt->get_result();

$campanhas = $resultado->fetch_assoc();

$stmt->close();


// =========================================
// ONGs FAVORITAS
// =========================================

$sqlFavoritos = "
    SELECT COUNT(*) AS total
    FROM favoritos
    WHERE idUsuarioComum = ?
";


$stmt = $conexao->prepare($sqlFavoritos);

$stmt->bind_param("i", $idUsuarioComum);

$stmt->execute();

$resultado = $stmt->get_result();

$favoritos = $resultado->fetch_assoc();

$stmt->close();


// =========================================
// CAUSAS APOIADAS
// =========================================

$sqlCausas = "
    SELECT c.nome

    FROM usuarioCausas uc

    INNER JOIN causas c
        ON uc.idCausa = c.idCausa

    WHERE uc.idUsuarioComum = ?

    ORDER BY c.nome
";


$stmt = $conexao->prepare($sqlCausas);

$stmt->bind_param("i", $idUsuarioComum);

$stmt->execute();

$resultado = $stmt->get_result();

$causas = [];

while ($linha = $resultado->fetch_assoc()) {

    $causas[] = $linha["nome"];

}

$stmt->close();


// =========================================
// DATA DE CRIAÇÃO
// =========================================

$dataCriacao = "-";

if (!empty($usuario["dataCriacao"])) {

    $data = new DateTime($usuario["dataCriacao"]);

    $dataCriacao = $data->format("d/m/Y");
}


// =========================================
// LOCALIZAÇÃO
// =========================================

$localizacao = "-";

if (!empty($usuario["cidade"]) && !empty($usuario["estado"])) {

    $localizacao =
        $usuario["cidade"] . ", " .
        $usuario["estado"];

}


// =========================================
// RESPOSTA
// =========================================

echo json_encode([

    "nome" => $usuario["nome"] ?: "-",

    "email" => $usuario["email"] ?: "-",

    "telefone" => $usuario["telefone"] ?: "-",

    "foto" => $usuario["fotoPerfil"] ?: "perfil.png",

    "dataCriacao" => $dataCriacao,

    "localizacao" => $localizacao,

    "totalDoado" => number_format(
        (float)$doado["total"],
        2,
        ",",
        "."
    ),

    "campanhasApoiadas" =>
        $campanhas["total"] ?: "0",

    "ongsFavoritas" =>
        $favoritos["total"] ?: "0",

    "causas" => $causas

], JSON_UNESCAPED_UNICODE);


$conexao->close();

?>