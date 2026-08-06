<?php
// whatsapp/diagnostico.php — Teste de conectividade com ApiBrasil
// REMOVA este arquivo após o diagnóstico!
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'maiara') {
    http_response_code(403); exit('Acesso negado.');
}

$host = 'gateway.apibrasil.io';
$url  = 'https://' . $host . '/api/v2/whatsmeow/send/text';

$testes = [];

/* 1. DNS */
$ip = gethostbyname($host);
$testes['dns'] = [
    'ok'  => ($ip !== $host),
    'msg' => ($ip !== $host) ? "Resolvido: $ip" : "FALHOU — hostname não resolvido",
];

/* 2. TCP porta 443 */
$sock = @fsockopen("ssl://$host", 443, $errno, $errstr, 5);
$testes['tcp_ssl'] = [
    'ok'  => (bool)$sock,
    'msg' => $sock ? "Porta 443 acessível" : "FALHOU — $errstr ($errno)",
];
if ($sock) fclose($sock);

/* 3. CURL com verificação SSL normal */
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '{}',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
curl_exec($ch);
$testes['curl_ssl_on'] = [
    'ok'       => (curl_errno($ch) === 0),
    'http'     => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'curl_err' => curl_error($ch) ?: 'nenhum',
];
curl_close($ch);

/* 4. CURL sem verificação SSL (diagnóstico de problema de certificado) */
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '{}',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);
curl_exec($ch);
$testes['curl_ssl_off'] = [
    'ok'       => (curl_errno($ch) === 0),
    'http'     => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'curl_err' => curl_error($ch) ?: 'nenhum',
];
curl_close($ch);

/* 5. Variáveis relevantes do ambiente */
$testes['ambiente'] = [
    'php'        => PHP_VERSION,
    'curl'       => curl_version()['version'],
    'ssl'        => curl_version()['ssl_version'],
    'cainfo'     => ini_get('curl.cainfo') ?: '(não configurado)',
    'capath'     => ini_get('curl.capath') ?: '(não configurado)',
    'open_basedir' => ini_get('open_basedir') ?: '(sem restrição)',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Diagnóstico WhatsApp</title>
<style>
body{font-family:monospace;background:#1a1a2e;color:#e0e0e0;padding:30px;max-width:800px;margin:auto}
h2{color:#25d366}
.ok{color:#25d366;font-weight:700}
.fail{color:#f87171;font-weight:700}
.bloco{background:#0f0f23;border:1px solid #333;border-radius:8px;padding:16px;margin:16px 0}
.label{color:#94a3b8;font-size:.85em;margin-bottom:4px}
pre{margin:0;white-space:pre-wrap;word-break:break-all;font-size:.88em}
a.btn{display:inline-block;margin-top:20px;padding:10px 20px;background:#25d366;color:#fff;border-radius:6px;text-decoration:none;font-weight:700}
</style>
</head>
<body>
<h2>Diagnóstico de Conectividade — ApiBrasil</h2>

<div class="bloco">
    <div class="label">1. DNS — Resolução de hostname</div>
    <span class="<?= $testes['dns']['ok'] ? 'ok' : 'fail' ?>"><?= $testes['dns']['ok'] ? '✔' : '✘' ?></span>
    <?= htmlspecialchars($testes['dns']['msg']) ?>
</div>

<div class="bloco">
    <div class="label">2. TCP — Conexão na porta 443</div>
    <span class="<?= $testes['tcp_ssl']['ok'] ? 'ok' : 'fail' ?>"><?= $testes['tcp_ssl']['ok'] ? '✔' : '✘' ?></span>
    <?= htmlspecialchars($testes['tcp_ssl']['msg']) ?>
</div>

<div class="bloco">
    <div class="label">3. CURL com SSL habilitado</div>
    <span class="<?= $testes['curl_ssl_on']['ok'] ? 'ok' : 'fail' ?>"><?= $testes['curl_ssl_on']['ok'] ? '✔' : '✘' ?></span>
    HTTP <?= $testes['curl_ssl_on']['http'] ?> — Erro CURL: <?= htmlspecialchars($testes['curl_ssl_on']['curl_err']) ?>
</div>

<div class="bloco">
    <div class="label">4. CURL sem SSL (apenas diagnóstico)</div>
    <span class="<?= $testes['curl_ssl_off']['ok'] ? 'ok' : 'fail' ?>"><?= $testes['curl_ssl_off']['ok'] ? '✔' : '✘' ?></span>
    HTTP <?= $testes['curl_ssl_off']['http'] ?> — Erro CURL: <?= htmlspecialchars($testes['curl_ssl_off']['curl_err']) ?>
</div>

<div class="bloco">
    <div class="label">5. Ambiente PHP/CURL</div>
    <pre><?= htmlspecialchars(json_encode($testes['ambiente'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
</div>

<?php
$dns_ok    = $testes['dns']['ok'];
$tcp_ok    = $testes['tcp_ssl']['ok'];
$ssl_on_ok = $testes['curl_ssl_on']['ok'];
$ssl_off_ok= $testes['curl_ssl_off']['ok'];
?>
<div class="bloco">
    <div class="label">Diagnóstico</div>
    <?php if (!$dns_ok): ?>
        <span class="fail">✘ DNS falhou.</span> O servidor PHP não consegue resolver o endereço. Verifique se há internet e se o DNS está configurado.
    <?php elseif (!$tcp_ok): ?>
        <span class="fail">✘ Porta 443 bloqueada.</span> Firewall ou provedor bloqueando conexões externas na porta HTTPS. Verifique o firewall do Windows/servidor.
    <?php elseif (!$ssl_on_ok && $ssl_off_ok): ?>
        <span class="fail">✘ Problema de certificado SSL.</span> CURL consegue conectar sem SSL mas falha com SSL ligado. Configure o <code>curl.cainfo</code> no php.ini apontando para um arquivo cacert.pem atualizado.
    <?php elseif ($ssl_on_ok || $ssl_off_ok): ?>
        <span class="ok">✔ Conexão funciona!</span> O CURL chega à API. O problema é de autenticação — verifique o token e o device_token no config.php, ou reconecte o dispositivo.
    <?php else: ?>
        <span class="fail">✘ Conexão totalmente bloqueada.</span> Nem com SSL desabilitado há resposta. Verifique firewall, proxy corporativo ou permissões do servidor.
    <?php endif; ?>
</div>

<a class="btn" href="../admin/whatsapp.php">← Voltar</a>
<p style="color:#64748b;font-size:.8em;margin-top:30px;">Lembre-se de excluir este arquivo após o diagnóstico.</p>
</body>
</html>
