<?php
session_start();
require_once 'conexao.php';

// Garante compatibilidade com o nome da variável de conexão ($conexao ou $conn)
$db = $conexao ?? $conn ?? null;

// ============================================================
// CONFIGURAÇÃO DA CHAVE PIX DA ONG
// ============================================================
define('PIX_CHAVE', '23722069823');
define('PIX_NOME_RECEBEDOR', 'Bem-Conecta_TCC');
define('PIX_CIDADE', 'Birigui');

// ============================================================
// FUNÇÕES QUE MONTAM O CÓDIGO PIX ("BR Code")
// ============================================================
function pixCampo(string $id, string $valor): string {
    $tamanho = str_pad(strlen($valor), 2, '0', STR_PAD_LEFT);
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

// ============================================================
// PASSO 1: Verificar se o formulário foi enviado e se há login
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inicialPage.php');
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../html/login.html');
    exit;
}

$idMUsuario = (int) $_SESSION['usuario_id'];

// ============================================================
// PASSO 2: Pegar os dados enviados pelo formulário
// ============================================================
$nome  = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$formaPagamento = trim($_POST['forma_pagamento'] ?? '');
$numeroCartao = '';
$tipoCartao = '';

$idPostRecebido     = (int) ($_POST['idPost'] ?? 0);
$idCampanhaRecebida = (int) ($_POST['idCampanha'] ?? 0);

$valorPersonalizado = trim($_POST['valor_personalizado'] ?? '');
$valorEscolhido     = trim($_POST['valor_escolhido'] ?? '');

$valor = ($valorPersonalizado !== '') ? $valorPersonalizado : $valorEscolhido;

// ============================================================
// PASSO 3: Validar os dados
// ============================================================
$erros = [];

if ($nome === '') {
    $erros[] = 'Por favor, informe seu nome.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = 'Por favor, informe um e-mail válido.';
}

if (!is_numeric($valor) || (float)$valor <= 0) {
    $erros[] = 'Por favor, escolha ou informe um valor de doação válido.';
}

if ($formaPagamento === 'cartao') {
    $numeroCartao = preg_replace('/\s+/', '', $_POST['numero_cartao'] ?? '');
    $nomeCartao   = trim($_POST['nome_cartao'] ?? '');
    $validade     = trim($_POST['validade_cartao'] ?? '');
    $cvv          = trim($_POST['cvv_cartao'] ?? '');
    $tipoCartao   = trim($_POST['tipo_cartao'] ?? '');

    if (!in_array($tipoCartao, ['credito', 'debito'], true)) {
        $erros[] = 'Selecione se o cartão é de crédito ou débito.';
    }
    if (strlen($numeroCartao) < 13 || !ctype_digit($numeroCartao)) {
        $erros[] = 'Número de cartão inválido.';
    }
    if ($nomeCartao === '') {
        $erros[] = 'Informe o nome impresso no cartão.';
    }
    if (!preg_match('/^\d{2}\/\d{2}$/', $validade)) {
        $erros[] = 'Validade do cartão inválida (use o formato MM/AA).';
    }
    if (!preg_match('/^\d{3,4}$/', $cvv)) {
        $erros[] = 'CVV inválido.';
    }
} elseif ($formaPagamento !== 'pix') {
    $erros[] = 'Selecione uma forma de pagamento válida.';
}

if (count($erros) > 0) {
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">';
    echo '<title>Erro na doação</title><link rel="stylesheet" href="../css/pagamento.css"></head><body>';
    echo '<div class="container"><div class="cabecalho"><h1>⚠️ Algo deu errado</h1></div><ul>';
    foreach ($erros as $erro) {
        echo '<li>' . htmlspecialchars($erro) . '</li>';
    }
    echo '</ul><br><a href="javascript:history.back()" class="botao-doar" style="display:block; text-align:center; text-decoration:none;">Voltar e tentar novamente</a></div></body></html>';
    exit;
}

$valor = number_format((float)$valor, 2, '.', '');
$valorFloat = (float) $valor;

// ============================================================
// PASSO 4: Salvar a doação no banco de dados
// ============================================================
try {
    if (!$db) {
        throw new Exception('Falha na conexão com o banco de dados.');
    }

    // 4.1. Busca o idUsuarioComum correspondente ao usuário logado (idMoldeUsuario)
    $stmtUser = $db->prepare("SELECT idUsuarioComum FROM UsuarioComum WHERE idMoldeUsuario = ?");
    $stmtUser->bind_param("i", $idMUsuario);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();
    $rowUser = $resUser->fetch_assoc();
    $stmtUser->close();

    if ($rowUser) {
        $idUsuarioComum = (int) $rowUser['idUsuarioComum'];
    } else {
        // Caso o usuário ainda não tenha registro em UsuarioComum, cria automaticamente para permitir a doação
        $stmtCriaUser = $db->prepare("INSERT INTO UsuarioComum (idMoldeUsuario) VALUES (?)");
        $stmtCriaUser->bind_param("i", $idMUsuario);
        $stmtCriaUser->execute();
        $idUsuarioComum = (int) $db->insert_id;
        $stmtCriaUser->close();
    }

    // 4.2. Descobre qual idCampanha receberá a doação
    $idCampanhaFinal = 0;

    // Se veio idCampanha direto no formulário, verifica se existe
    if ($idCampanhaRecebida > 0) {
        $stmtCamp = $db->prepare("SELECT idCampanha FROM campanhas WHERE idCampanha = ?");
        $stmtCamp->bind_param("i", $idCampanhaRecebida);
        $stmtCamp->execute();
        if ($stmtCamp->get_result()->fetch_assoc()) {
            $idCampanhaFinal = $idCampanhaRecebida;
        }
        $stmtCamp->close();
    }

    // Se veio idPost (do botão "Doar Agora" do feed), busca a campanha ou a ONG do post
    if ($idCampanhaFinal === 0 && $idPostRecebido > 0) {
        $stmtPost = $db->prepare("SELECT idCampanha, idONG, titulo FROM posts WHERE idPost = ?");
        $stmtPost->bind_param("i", $idPostRecebido);
        $stmtPost->execute();
        $postData = $stmtPost->get_result()->fetch_assoc();
        $stmtPost->close();

        if ($postData) {
            if (!empty($postData['idCampanha'])) {
                $idCampanhaFinal = (int) $postData['idCampanha'];
            } else {
                $idONGPost = (int) $postData['idONG'];
                // Procura uma campanha existente dessa ONG
                $stmtCampOng = $db->prepare("SELECT idCampanha FROM campanhas WHERE idONG = ? LIMIT 1");
                $stmtCampOng->bind_param("i", $idONGPost);
                $stmtCampOng->execute();
                $campOng = $stmtCampOng->get_result()->fetch_assoc();
                $stmtCampOng->close();

                if ($campOng) {
                    $idCampanhaFinal = (int) $campOng['idCampanha'];
                } else {
                    // Cria uma campanha vinculada ao post/ONG para satisfazer a chave estrangeira
                    $tituloCamp = mb_substr($postData['titulo'], 0, 100);
                    $stmtNovaCamp = $db->prepare("INSERT INTO campanhas (idONG, titulo, descricao) VALUES (?, ?, 'Doação via publicação no Bem Conecta.')");
                    $stmtNovaCamp->bind_param("is", $idONGPost, $tituloCamp);
                    $stmtNovaCamp->execute();
                    $idCampanhaFinal = (int) $db->insert_id;
                    $stmtNovaCamp->close();
                }
            }
        }
    }

    // Fallback: se o usuário abriu o pagamento sem post/campanha específica, usa a primeira campanha ativa
    if ($idCampanhaFinal === 0) {
        $resFallback = $db->query("SELECT idCampanha FROM campanhas ORDER BY idCampanha ASC LIMIT 1");
        if ($resFallback && ($rowCamp = $resFallback->fetch_assoc())) {
            $idCampanhaFinal = (int) $rowCamp['idCampanha'];
        } else {
            // Se não existir nenhuma campanha no banco ainda, vincula à primeira ONG cadastrada
            $resOng = $db->query("SELECT idONG FROM ONGs ORDER BY idONG ASC LIMIT 1");
            if ($resOng && ($rowOng = $resOng->fetch_assoc())) {
                $idOngPadrao = (int) $rowOng['idONG'];
                $stmtCampGeral = $db->prepare("INSERT INTO campanhas (idONG, titulo, descricao) VALUES (?, 'Doação Geral Bem Conecta', 'Doação realizada pela plataforma.')");
                $stmtCampGeral->bind_param("i", $idOngPadrao);
                $stmtCampGeral->execute();
                $idCampanhaFinal = (int) $db->insert_id;
                $stmtCampGeral->close();
            } else {
                throw new Exception('Nenhuma ONG ou campanha cadastrada no banco para receber a doação.');
            }
        }
    }

    // 4.3. Insere a doação na tabela doacoes (dataDoacao é preenchida automaticamente pelo banco)
    $stmtDoacao = $db->prepare("INSERT INTO doacoes (idUsuarioComum, idCampanha, valor) VALUES (?, ?, ?)");
    $stmtDoacao->bind_param("iid", $idUsuarioComum, $idCampanhaFinal, $valorFloat);
    $stmtDoacao->execute();
    $stmtDoacao->close();

} catch (Throwable $e) {
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">';
    echo '<title>Erro ao registrar doação</title><link rel="stylesheet" href="../css/pagamento.css"></head><body>';
    echo '<div class="container"><div class="cabecalho"><h1>⚠️ Erro ao salvar doação</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p></div>';
    echo '<a href="javascript:history.back()" class="botao-doar" style="display:block; text-align:center; text-decoration:none;">Voltar</a></div></body></html>';
    exit;
}

// ============================================================
// PASSO 5: Preparar dados para exibição da confirmação
// ============================================================
$resumoPagamento = $formaPagamento;
if ($formaPagamento === 'cartao') {
    $ultimosDigitos = substr($numeroCartao, -4);
    $tipoCartaoTexto = ($tipoCartao === 'debito') ? 'débito' : 'crédito';
    $resumoPagamento = "cartão de $tipoCartaoTexto terminado em $ultimosDigitos";
}

$pixCodigo = '';
$pixQrCodeUrl = '';
if ($formaPagamento === 'pix') {
    $resumoPagamento = 'pix';
    $pixCodigo = pixGerarCodigo(PIX_CHAVE, PIX_NOME_RECEBEDOR, PIX_CIDADE, $valor);
    $pixQrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($pixCodigo);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Obrigado pela sua doação!</title>
    <link rel="stylesheet" href="../css/pagamento.css">
</head>
<body>

    <div class="container">
        <div class="cabecalho">
            <h1>✅ Obrigado, <?php echo htmlspecialchars($nome); ?>!</h1>
            <p>Sua doação de <strong>R$ <?php echo htmlspecialchars(number_format($valorFloat, 2, ',', '.')); ?></strong> via <strong><?php echo htmlspecialchars($resumoPagamento); ?></strong> foi registrada com sucesso.</p>
            <p>Enviamos um e-mail de confirmação para <?php echo htmlspecialchars($email); ?>.</p>
        </div>

        <?php if ($formaPagamento === 'pix') : ?>
            <hr>
            <div class="bloco-pix-qr">
                <label class="titulo-secao">Escaneie para pagar com Pix:</label>
                <img src="<?php echo htmlspecialchars($pixQrCodeUrl); ?>" alt="QR Code para pagamento via Pix" class="qr-code-imagem">

                <label class="titulo-secao">Ou copie o código abaixo (Pix Copia e Cola):</label>
                <textarea readonly class="pix-copia-cola"><?php echo htmlspecialchars($pixCodigo); ?></textarea>
                <p class="texto-info">Toque no campo acima e segure para selecionar e copiar o código.</p>
            </div>
        <?php endif; ?>

        <a href="../php/perfil.php" class="botao-doar" style="display:block; text-align:center; text-decoration:none;">
            Ver no Meu Perfil
        </a>
    </div>

</body>
</html>