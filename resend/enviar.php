<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

function h(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function umaLinha(string $texto): string
{
    return trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
}

function registrarLog(array $config, string $destinatario, string $status, string $detalhe): void
{
    $linha = sprintf(
        "%s | %s | %s | %s\n",
        date('Y-m-d H:i:s'),
        umaLinha($destinatario),
        $status,
        umaLinha($detalhe)
    );

    file_put_contents($config['log'], $linha, FILE_APPEND | LOCK_EX);
}

function mensagemParaUsuario(string $status): string
{
    return match ($status) {
        'rede' => 'Não foi possível conectar ao serviço de e-mail. Verifique sua internet e tente novamente.',
        '401' => 'O envio de e-mails está mal configurado no servidor (chave inválida). Avise o administrador do site.',
        '403' => 'Este endereço de e-mail não pode receber mensagens no momento. Tente outro e-mail ou fale com o administrador.',
        '422' => 'Algum dado da mensagem está faltando ou é inválido. Revise o formulário e tente de novo.',
        '429' => 'Muitas mensagens foram enviadas em pouco tempo. Aguarde alguns minutos e tente novamente.',
        default => 'Não foi possível enviar a mensagem agora. Tente novamente mais tarde.',
    };
}

function chamarApi(array $config, array $dados): array
{
    $ch = curl_init($config['api_url']);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($dados),
    ]);

    $resposta = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);

    curl_close($ch);

    $json = is_string($resposta) ? json_decode($resposta, true) : null;

    return [
        'status' => $status,
        'json' => is_array($json) ? $json : [],
        'erroCurl' => $erroCurl,
    ];
}

function enviarERegistrar(array $config, array $dados, string $destinatario): array
{
    $r = chamarApi($config, $dados);

    if ($r['erroCurl'] !== '') {
        registrarLog($config, $destinatario, 'rede', $r['erroCurl']);

        return [
            'ok' => false,
            'codigo' => 'rede',
        ];
    }

    if ($r['status'] >= 200 && $r['status'] < 300) {
        registrarLog(
            $config,
            $destinatario,
            (string) $r['status'],
            (string) ($r['json']['id'] ?? 'sem id')
        );

        return [
            'ok' => true,
            'codigo' => (string) $r['status'],
        ];
    }

    registrarLog(
        $config,
        $destinatario,
        (string) $r['status'],
        (string) ($r['json']['message'] ?? 'sem mensagem')
    );

    return [
        'ok' => false,
        'codigo' => (string) $r['status'],
    ];
}

function montarHtml(string $titulo, string $conteudoHtml, string $rodape): string
{
    return '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#f4f4f4;">'
        . '<tr><td align="center" style="padding:16px;">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;border-collapse:collapse;font-family:Arial,sans-serif;background:#ffffff;">'
        . '<tr><td style="background:#0F2E5C;color:#ffffff;padding:20px;"><h2 style="margin:0;font-size:20px;">' . h($titulo) . '</h2></td></tr>'
        . '<tr><td style="padding:20px;color:#333333;line-height:1.5;font-size:15px;">' . $conteudoHtml . '</td></tr>'
        . '<tr><td style="background:#f4f4f4;padding:14px;font-size:12px;color:#777777;">' . h($rodape) . '</td></tr>'
        . '</table></td></tr></table>';
}

function lerAnexo(): array
{
    if (!isset($_FILES['anexo']) || $_FILES['anexo']['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    $f = $_FILES['anexo'];

    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return [null, 'Não foi possível receber o anexo.'];
    }

    if ($f['size'] > 2 * 1024 * 1024) {
        return [null, 'O anexo passa de 2 MB.'];
    }

    $permitidos = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
    ];

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);

    if (!isset($permitidos[$ext]) || !in_array($mime, $permitidos[$ext], true)) {
        return [null, 'Só aceitamos anexos PDF, PNG ou JPG.'];
    }

    $nomeSeguro = preg_replace(
        '/[^A-Za-z0-9._-]/',
        '_',
        basename($f['name'])
    );

    return [[
        'filename' => $nomeSeguro,
        'content' => base64_encode(file_get_contents($f['tmp_name'])),
    ], null];
}

function pagina(string $titulo, string $mensagem, bool $ok, int $http = 200): never
{
    http_response_code($http);

    $cor = $ok ? '#1a7f4b' : '#b3261e';

    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . h($titulo) . '</title></head>'
        . '<body style="font-family:Arial,sans-serif;background:#f4f6fa;padding:24px;">'
        . '<div style="max-width:480px;margin:60px auto;background:#fff;padding:28px;border-radius:10px;box-shadow:0 2px 12px #0001;">'
        . '<h1 style="margin-top:0;font-size:22px;color:' . $cor . ';">' . h($titulo) . '</h1>'
        . '<p style="line-height:1.5;">' . h($mensagem) . '</p>'
        . '<p><a href="index.html">&larr; Voltar ao formulário</a></p>'
        . '</div></body></html>';

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pagina(
        'Método não permitido',
        'Use o formulário para enviar sua mensagem.',
        false,
        405
    );
}

$nome = mb_substr(umaLinha($_POST['nome'] ?? ''), 0, 100);
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$mensagem = mb_substr(trim($_POST['mensagem'] ?? ''), 0, 5000);

if ($nome === '' || !$email || $mensagem === '') {
    pagina(
        'Dados incompletos',
        'Preencha nome, e-mail válido e mensagem.',
        false,
        422
    );
}

[$anexo, $erroAnexo] = lerAnexo();

if ($erroAnexo !== null) {
    pagina('Anexo recusado', $erroAnexo, false, 422);
}

$conteudo = '<p><strong>Nome:</strong> ' . h($nome) . '</p>'
    . '<p><strong>E-mail:</strong> ' . h($email) . '</p>'
    . '<p>' . nl2br(h($mensagem)) . '</p>';

$dados = [
    'from' => $config['remetente'],
    'to' => [$config['destino']],
    'subject' => 'Contato do site — ' . $nome,
    'reply_to' => [$email],
    'html' => montarHtml(
        'Contato do site',
        $conteudo,
        'Enviado pelo formulário do site.'
    ),
    'text' => "Nome: $nome\nE-mail: $email\n\n$mensagem",
];

if ($anexo !== null) {
    $dados['attachments'] = [$anexo];
}

$principal = enviarERegistrar(
    $config,
    $dados,
    $config['destino']
);

if (!$principal['ok']) {
    pagina(
        'Não foi possível enviar',
        mensagemParaUsuario($principal['codigo']),
        false,
        502
    );
}

$confirmacao = [
    'from' => $config['remetente'],
    'to' => [$email],
    'subject' => 'Recebemos sua mensagem',
    'html' => montarHtml(
        'Mensagem recebida',
        '<p>Olá, ' . h($nome) . '! Recebemos sua mensagem e responderemos em breve.</p>'
        . '<p style="color:#777777;">Sua mensagem:</p><p>' . nl2br(h($mensagem)) . '</p>',
        'Esta é uma confirmação automática.'
    ),
    'text' => "Olá, $nome! Recebemos sua mensagem e responderemos em breve.\n\nSua mensagem:\n$mensagem",
];

$copia = enviarERegistrar(
    $config,
    $confirmacao,
    $email
);

$aviso = $copia['ok']
    ? 'Sua mensagem foi enviada e você receberá uma confirmação por e-mail.'
    : 'Sua mensagem foi enviada, mas não conseguimos mandar o e-mail de confirmação para você.';

pagina('Mensagem enviada!', $aviso, true);
