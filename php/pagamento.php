<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json; charset=utf-8');

$db = $conexao ?? $conn ?? null;

// ============================================================
// CONFIGURAÇÃO DA CHAVE PIX
// ============================================================
define('PIX_CHAVE', '23722069823');
define('PIX_NOME_RECEBEDOR', 'Bem-Conecta_TCC');
define('PIX_CIDADE', 'Birigui');

function pixCampo(string $id, string $valor): string {
    $tamanho = str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT);
    return $id . $tamanho . $valor;
}

function pixCrc16(string $payload): string {
    $polinomio = 0x1021;
    $resultado = 0xFFFF;

    for ($i = 0; $i < strlen($payload); $i++) {
        $resultado ^= (ord($payload[$i]) << 8);
        for ($j = 0; $j < 8; $j++) {
            if (($resultado & 0x8000) !== 0) {
                $resultado = (($resultado << 1) ^ $polinomio) & 0xFFFF;
            } else {
                $resultado = ($resultado << 1) & 0xFFFF;
            }
        }
    }

    return strtoupper(str_pad(dechex($resultado), 4, '0', STR_PAD_LEFT));
}

function pixGerarCodigo(string $chave, string $nomeRecebedor, string $cidade, string $valor): string {
    $payload  = pixCampo('00', '01');
    $payload .= pixCampo('26', pixCampo('00', 'br.gov.bcb.pix') . pixCampo('01', $chave));
    $payload .= pixCampo('52', '0000');
    $payload .= pixCampo('53', '986');
    $payload .= pixCampo('54', $valor);
    $payload .= pixCampo('58', 'BR');
    $payload .= pixCampo('59', substr($nomeRecebedor, 0, 25));
    $payload .= pixCampo('60', substr($cidade, 0, 15));
    $payload .= pixCampo('62', pixCampo('05', '***'));
    $payload .= '6304';
    $payload .= pixCrc16($payload);

    return $payload;
}

function normalizarFotoOng(?string $foto): string {
    if (empty($foto) || in_array($foto, ['perfil.png', 'img/perfil/perfil.png'], true)) {
        return '../img/jpg/ftPerfil.jpg';
    }
    if (str_starts_with($foto, '../') || str_starts_with($foto, 'http')) {
        return $foto;
    }
    return '../' . ltrim($foto, '/');
}

// ============================================================
// VERIFICA LOGIN
// ============================================================
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode([
        'sucesso' => false,
        'redirect' => '../html/login.html',
        'erro' => 'Sua sessão expirou. Faça login para continuar.'
    ]);
    exit;
}

if (!$db) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro de conexão com o banco de dados.']);
    exit;
}

$idMUsuario = (int) $_SESSION['usuario_id'];
$acao = $_REQUEST['acao'] ?? 'info';

// ============================================================
// AÇÃO 1: CARREGAR INFORMAÇÕES DA ONG / CAMPANHA / USUÁRIO
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $acao === 'info') {
    $idPost = (int) ($_GET['idPost'] ?? 0);
    $idCampanha = (int) ($_GET['idCampanha'] ?? 0);
    $idONG = (int) ($_GET['idONG'] ?? 0);

    // Dados do usuário logado
    $usuarioNome = $_SESSION['usuario_nome'] ?? '';$usuarioEmail = '';
    $stmtU =$db->prepare("SELECT nome, email FROM MoldeUsuario WHERE idMUsuario = ? LIMIT 1");
    if ($stmtU) {$stmtU->bind_param("i", $idMUsuario);$stmtU->execute();
        if ($uRow =$stmtU->get_result()->fetch_assoc()) {
            $usuarioNome =$uRow['nome'];
            $usuarioEmail =$uRow['email'];
        }
        $stmtU->close();
    }

    $destino = null;

    // 1) Se veio idCampanha na URL
    if ($idCampanha > 0) {
        $stmt =$db->prepare("
            SELECT
                c.idCampanha,
                c.titulo AS tituloCampanha,
                o.idONG,
                mu.nome AS nomeONG,
                mu.fotoPerfil AS fotoONG
            FROM campanhas c
            INNER JOIN ONGs o ON c.idONG = o.idONG
            INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
            WHERE c.idCampanha = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $idCampanha);
        $stmt->execute();$destino = $stmt->get_result()->fetch_assoc();$stmt->close();
    }

    // 2) Se veio idPost na URL
    if (!$destino &&$idPost > 0) {
        $stmt =$db->prepare("
            SELECT
                p.idPost,
                p.idCampanha,
                c.titulo AS tituloCampanha,
                o.idONG,
                mu.nome AS nomeONG,
                mu.fotoPerfil AS fotoONG
            FROM posts p
            INNER JOIN ONGs o ON p.idONG = o.idONG
            INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
            LEFT JOIN campanhas c ON p.idCampanha = c.idCampanha
            WHERE p.idPost = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $idPost);
        $stmt->execute();$destino = $stmt->get_result()->fetch_assoc();$stmt->close();
    }

    // 3) Se veio idONG na URL
    if (!$destino &&$idONG > 0) {
        $stmt =$db->prepare("
            SELECT
                0 AS idCampanha,
                '' AS tituloCampanha,
                o.idONG,
                mu.nome AS nomeONG,
                mu.fotoPerfil AS fotoONG
            FROM ONGs o
            INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
            WHERE o.idONG = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $idONG);
        $stmt->execute();$destino = $stmt->get_result()->fetch_assoc();$stmt->close();
    }

    // Lista de campanhas/ONGs disponíveis caso o usuário abra "Doar" sem link específico
    $opcoesDestino = [];
    $resOpcoes =$db->query("
        SELECT
            c.idCampanha,
            c.titulo AS tituloCampanha,
            o.idONG,
            mu.nome AS nomeONG,
            mu.fotoPerfil AS fotoONG
        FROM campanhas c
        INNER JOIN ONGs o ON c.idONG = o.idONG
        INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
        WHERE c.status = 'ativa'
        ORDER BY c.dataInicio DESC
    ");
    if ($resOpcoes) {
        while ($row =$resOpcoes->fetch_assoc()) {
            $row['fotoONG'] = normalizarFotoOng($row['fotoONG']);
            $opcoesDestino[] =$row;
        }
    }

    // Se ainda não há destino definido, usa a primeira opção ativa ou a primeira ONG
    if (!$destino) {
        if (!empty($opcoesDestino)) {
            $destino =$opcoesDestino[0];
        } else {
            $resFirstOng =$db->query("
                SELECT
                    0 AS idCampanha,
                    '' AS tituloCampanha,
                    o.idONG,
                    mu.nome AS nomeONG,
                    mu.fotoPerfil AS fotoONG
                FROM ONGs o
                INNER JOIN MoldeUsuario mu ON o.idMoldeUsuario = mu.idMUsuario
                ORDER BY o.idONG ASC
                LIMIT 1
            ");
            if ($resFirstOng) {
                $destino =$resFirstOng->fetch_assoc();
            }
        }
    }

    if ($destino) {
        $destino['fotoONG'] = normalizarFotoOng($destino['fotoONG'] ?? null);
        $destino['idCampanha'] = (int) ($destino['idCampanha'] ?? 0);
        $destino['idONG'] = (int) ($destino['idONG'] ?? 0);
        $destino['tituloCampanha'] =$destino['tituloCampanha'] ?? '';
    }

    echo json_encode([
        'sucesso' => true,
        'destino' => $destino,
        'opcoesDestino' => $opcoesDestino,
        'usuario' => [
            'nome' => $usuarioNome,
            'email' => $usuarioEmail
        ]
    ]);
    exit;
}

// ============================================================
// AÇÃO 2: GERAR QR CODE E CÓDIGO PIX PARA A ETAPA 2
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' &&$acao === 'gerar_pix') {
    $valor = (float) ($_GET['valor'] ?? 0);
    if ($valor <= 0) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Informe um valor válido para gerar o Pix.']);
        exit;
    }

    $valorFormatado = number_format($valor, 2, '.', '');
    $pixCodigo = pixGerarCodigo(PIX_CHAVE, PIX_NOME_RECEBEDOR, PIX_CIDADE,$valorFormatado);
    $pixQrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=230x230&data=' . urlencode($pixCodigo);

    echo json_encode([
        'sucesso' => true,
        'pixCodigo' => $pixCodigo,
        'pixQrCodeUrl' => $pixQrCodeUrl,
        'chave' => PIX_CHAVE,
        'recebedor' => PIX_NOME_RECEBEDOR
    ]);
    exit;
}

// ============================================================
// AÇÃO 3: AUTENTICAR E REGISTRAR A DOAÇÃO NO BANCO
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {$dados = json_decode(file_get_contents('php://input'), true);
    if (!is_array($dados)) {
        $dados =$_POST;
    }

    $nome = trim($dados['nome'] ?? '');
    $email = trim($dados['email'] ?? '');
    $formaPagamento = trim($dados['forma_pagamento'] ?? '');
    $valorFloat = (float) ($dados['valor'] ?? 0);

    $idPostRecebido = (int) ($dados['idPost'] ?? 0);
    $idCampanhaRecebida = (int) ($dados['idCampanha'] ?? 0);
    $idOngRecebida = (int) ($dados['idONG'] ?? 0);

    $numeroCartao = '';$tipoCartao = '';

    if ($nome === '') {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Informe seu nome completo.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Informe um e-mail válido.']);
        exit;
    }

    if ($valorFloat <= 0) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Informe um valor de doação maior que zero.']);
        exit;
    }

    if ($formaPagamento === 'cartao') {
        $numeroCartao = preg_replace('/\D/', '',$dados['numero_cartao'] ?? '');
        $nomeCartao = trim($dados['nome_cartao'] ?? '');
        $validade = trim($dados['validade_cartao'] ?? '');
        $cvv = preg_replace('/\D/', '',$dados['cvv_cartao'] ?? '');
        $tipoCartao = trim($dados['tipo_cartao'] ?? '');

        if (!in_array($tipoCartao, ['credito', 'debito'], true)) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'Selecione Crédito ou Débito.']);
            exit;
        }
        if (strlen($numeroCartao) < 13 || strlen($numeroCartao) > 19) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'Número de cartão inválido.']);
            exit;
        }
        if ($nomeCartao === '') {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'Informe o nome impresso no cartão.']);
            exit;
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/',$validade)) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'Validade inválida. Use MM/AA.']);
            exit;
        }
        if (strlen($cvv) < 3 || strlen($cvv) > 4) {
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'erro' => 'CVV inválido.']);
            exit;
        }
    } elseif ($formaPagamento !== 'pix') {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Forma de pagamento inválida.']);
        exit;
    }

    try {
        // 1. Garante registro em UsuarioComum
        $stmtUser =$db->prepare("SELECT idUsuarioComum FROM UsuarioComum WHERE idMoldeUsuario = ? LIMIT 1");
        $stmtUser->bind_param("i", $idMUsuario);
        $stmtUser->execute();$rowUser = $stmtUser->get_result()->fetch_assoc();$stmtUser->close();

        if ($rowUser) {
            $idUsuarioComum = (int)$rowUser['idUsuarioComum'];
        } else {
            $stmtCriaUser =$db->prepare("INSERT INTO UsuarioComum (idMoldeUsuario) VALUES (?)");
            $stmtCriaUser->bind_param("i", $idMUsuario);$stmtCriaUser->execute();
            $idUsuarioComum = (int)$db->insert_id;
            $stmtCriaUser->close();
        }

        // 2. Resolve idCampanha obrigatório na tabela doacoes
        $idCampanhaFinal = 0;

        if ($idCampanhaRecebida > 0) {
            $stmtCamp =$db->prepare("SELECT idCampanha FROM campanhas WHERE idCampanha = ? LIMIT 1");
            $stmtCamp->bind_param("i", $idCampanhaRecebida);$stmtCamp->execute();
            if ($stmtCamp->get_result()->fetch_assoc()) {
                $idCampanhaFinal =$idCampanhaRecebida;
            }
            $stmtCamp->close();
        }

        if ($idCampanhaFinal === 0 &&$idPostRecebido > 0) {
            $stmtPost =$db->prepare("SELECT idCampanha, idONG, titulo FROM posts WHERE idPost = ? LIMIT 1");
            $stmtPost->bind_param("i", $idPostRecebido);
            $stmtPost->execute();$postData = $stmtPost->get_result()->fetch_assoc();$stmtPost->close();

            if ($postData) {
                if (!empty($postData['idCampanha'])) {
                    $idCampanhaFinal = (int)$postData['idCampanha'];
                } else {
                    $idOngRecebida = (int)$postData['idONG'];
                }
            }
        }

        if ($idCampanhaFinal === 0 &&$idOngRecebida > 0) {
            $stmtCampOng =$db->prepare("SELECT idCampanha FROM campanhas WHERE idONG = ? ORDER BY idCampanha ASC LIMIT 1");
            $stmtCampOng->bind_param("i", $idOngRecebida);
            $stmtCampOng->execute();$campOng = $stmtCampOng->get_result()->fetch_assoc();$stmtCampOng->close();

            if ($campOng) {
                $idCampanhaFinal = (int)$campOng['idCampanha'];
            } else {
                $stmtNovaCamp =$db->prepare("INSERT INTO campanhas (idONG, titulo, descricao) VALUES (?, 'Apoio Direto à ONG', 'Doação realizada pela plataforma Bem Conecta.')");
                $stmtNovaCamp->bind_param("i", $idOngRecebida);$stmtNovaCamp->execute();
                $idCampanhaFinal = (int)$db->insert_id;
                $stmtNovaCamp->close();
            }
        }

        if ($idCampanhaFinal === 0) {
            $resFallback =$db->query("SELECT idCampanha FROM campanhas ORDER BY idCampanha ASC LIMIT 1");
            if ($resFallback && ($rowCamp =$resFallback->fetch_assoc())) {
                $idCampanhaFinal = (int)$rowCamp['idCampanha'];
            } else {
                throw new Exception('Nenhuma ONG ou campanha disponível para receber a doação.');
            }
        }

        // 3. Registra a doação no banco
        $stmtDoacao =$db->prepare("INSERT INTO doacoes (idUsuarioComum, idCampanha, valor) VALUES (?, ?, ?)");
        $stmtDoacao->bind_param("iid", $idUsuarioComum,$idCampanhaFinal, $valorFloat);$stmtDoacao->execute();
        $idDoacaoGerada = (int)$db->insert_id;
        $stmtDoacao->close();

        $resumoPagamento = 'Pix (Autenticado)';
        if ($formaPagamento === 'cartao') {
            $ultimos = substr($numeroCartao, -4);
            $tipoTxt = ($tipoCartao === 'debito') ? 'Débito' : 'Crédito';
            $resumoPagamento = "Cartão de $tipoTxt (•••• $ultimos)";
        }

        echo json_encode([
            'sucesso' => true,
            'comprovante' => [
                'codigo' => 'BC-' . str_pad((string) $idDoacaoGerada, 6, '0', STR_PAD_LEFT),
                'valor' => number_format($valorFloat, 2, ',', '.'),
                'formaPagamento' =>$resumoPagamento,
                'doador' => $nome,
                'email' => $email,
                'dataHora' => date('d/m/Y \à\s H:i')
            ]
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Erro ao registrar doação: ' . $e->getMessage()
        ]);
    }
    exit;
}