<?php
// ============================================================
// CONFIGURAÇÃO DA CHAVE PIX DA ONG
// ============================================================
define('PIX_CHAVE', '23722069823');
define('PIX_NOME_RECEBEDOR', 'Bem-Conecta_TCC');
define('PIX_CIDADE', 'Birigui');

// ============================================================
// FUNÇÕES QUE MONTAM O CÓDIGO PIX ("BR Code")
// Isso segue o padrão oficial do Banco Central para QR Code
// estático de Pix. Não precisa de nenhum gateway pago: é só
// montar o texto no formato certo e calcular o checksum (CRC16).
// ============================================================

// Monta um "campo" no formato ID + Tamanho + Valor, exigido pelo padrão
function pixCampo(string $id, string $valor): string {
    $tamanho = str_pad(strlen($valor), 2, '0', STR_PAD_LEFT);
    return $id . $tamanho . $valor;
}

// Calcula o checksum CRC16 que fica no final do código Pix
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

// Monta o código Pix completo ("copia e cola") com o valor da doação
function pixGerarCodigo(string $chave, string $nomeRecebedor, string $cidade, string $valor): string {
    $payload  = pixCampo('00', '01'); // formato do payload
    $payload .= pixCampo('26', pixCampo('00', 'br.gov.bcb.pix') . pixCampo('01', $chave)); // dados da chave
    $payload .= pixCampo('52', '0000'); // categoria do comerciante (genérica)
    $payload .= pixCampo('53', '986');  // moeda: Real (BRL)
    $payload .= pixCampo('54', $valor); // valor da doação
    $payload .= pixCampo('58', 'BR');   // país
    $payload .= pixCampo('59', substr($nomeRecebedor, 0, 25)); // nome do recebedor
    $payload .= pixCampo('60', substr($cidade, 0, 15));        // cidade do recebedor
    $payload .= pixCampo('62', pixCampo('05', '***'));         // identificador da transação (genérico)
    $payload .= '6304'; // marca o início do checksum (ID 63, tamanho 04)
    $payload .= pixCrc16($payload); // adiciona o checksum calculado

    return $payload;
}

// ============================================================
// PASSO 1: Verificar se o formulário foi realmente enviado
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

// ============================================================
// PASSO 2: Pegar os dados enviados pelo formulário
// ============================================================
$nome  = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$formaPagamento = trim($_POST['forma_pagamento'] ?? '');
$numeroCartao = ''; // valor inicial, evita aviso de "variável possivelmente indefinida"
$tipoCartao = '';   // idem

// Decide qual valor usar: o personalizado (se preenchido) ou o valor fixo escolhido
$valorPersonalizado = trim($_POST['valor_personalizado'] ?? '');
$valorEscolhido      = trim($_POST['valor_escolhido'] ?? '');

if ($valorPersonalizado !== '') {
    $valor = $valorPersonalizado;
} else {
    $valor = $valorEscolhido;
}

// ============================================================
// PASSO 3: Validar os dados (checagens simples)
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

// Validações extras de acordo com a forma de pagamento escolhida
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

// Se houver erro, mostra mensagem e para por aqui
if (count($erros) > 0) {
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8">';
    echo '<title>Erro na doação</title><link rel="stylesheet" href="style.css"></head><body>';
    echo '<div class="container"><div class="cabecalho"><h1>⚠️ Algo deu errado</h1></div><ul>';
    foreach ($erros as $erro) {
        echo '<li>' . htmlspecialchars($erro) . '</li>';
    }
    echo '</ul><br><a href="index.html">Voltar e tentar novamente</a></div></body></html>';
    exit;
}

// Formata o valor com 2 casas decimais
$valor = number_format((float)$valor, 2, '.', '');

// ============================================================
// PASSO 4: Processar o pagamento
// ============================================================
// ATENÇÃO MUITO IMPORTANTE:
// Nunca envie ou armazene o número completo do cartão, validade
// ou CVV no seu próprio servidor/banco de dados. Isso viola as
// normas PCI-DSS e coloca os doadores em risco.
//
// O jeito certo de fazer isso é usar o "Checkout Transparente" ou
// SDK de JavaScript do gateway de pagamento (Mercado Pago, Stripe,
// PagSeguro etc). Esses SDKs trocam o número do cartão por um
// "token" direto no navegador do usuário, e é esse token (não o
// cartão) que chega até o seu PHP. Exemplo do que normalmente se
// faz aqui:
//
//   $resultado = $gatewayDePagamento->cobrar([
//       'token' => $_POST['token_do_cartao'], // veio do JS do gateway
//       'valor' => $valor,
//       'nome'  => $nome,
//       'email' => $email,
//   ]);
//
// Por enquanto, para fins de demonstração, vamos apenas simular
// que a doação foi recebida e registrar um resumo (sem guardar
// número de cartão, validade ou CVV).

$resumoPagamento = $formaPagamento;
if ($formaPagamento === 'cartao') {
    $ultimosDigitos = substr($numeroCartao, -4);
    $tipoCartaoTexto = ($tipoCartao === 'debito') ? 'debito' : 'credito';
    $resumoPagamento = "cartao de $tipoCartaoTexto terminado em $ultimosDigitos";
}

// Se a forma escolhida foi Pix, gera o código e a imagem do QR Code
$pixCodigo = '';
$pixQrCodeUrl = '';
if ($formaPagamento === 'pix') {
    $resumoPagamento = 'pix';
    $pixCodigo = pixGerarCodigo(PIX_CHAVE, PIX_NOME_RECEBEDOR, PIX_CIDADE, $valor);

    // Usamos um serviço gratuito que transforma texto em imagem de QR Code.
    // Não precisa de nenhuma biblioteca instalada no servidor.
    $pixQrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($pixCodigo);
}

$linha = date('d/m/Y H:i:s') . " | Nome: $nome | Email: $email | Valor: R$ $valor | Pagamento: $resumoPagamento" . PHP_EOL;
file_put_contents('doacoes.txt', $linha, FILE_APPEND);

// ============================================================
// PASSO 5: Mostrar página de confirmação
// ============================================================
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
            <p>Sua doação de <strong>R$ <?php echo htmlspecialchars($valor); ?></strong> via <strong><?php echo htmlspecialchars($resumoPagamento); ?></strong> foi registrada com sucesso.</p>
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

        <a href="../index.html" class="botao-doar" style="display:block; text-align:center; text-decoration:none;">
            Voltar ao início
        </a>
    </div>

</body>
</html>