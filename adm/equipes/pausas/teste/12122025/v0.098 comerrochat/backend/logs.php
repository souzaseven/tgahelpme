<?php
// =============================================================
// logs.php — Visualização e limpeza dos logs do Controle de Pausa
// =============================================================

header('Content-Type: text/html; charset=utf-8');

// Pasta onde os logs são salvos
$dirLogs = __DIR__ . "/logs";

// Garante que exista
if (!is_dir($dirLogs)) {
    mkdir($dirLogs, 0775, true);
}

// Lê lista de arquivos
$arquivos = array_diff(scandir($dirLogs), ['.', '..']);


// =============================================================
// LIMPAR LOGS COM MAIS DE 30 DIAS
// =============================================================
$mensagem = "";

if (isset($_GET['limpar'])) {

    $diasMax = 30;
    $hoje = time();
    $apagados = 0;

    foreach ($arquivos as $arq) {
        $path = "$dirLogs/$arq";

        // Ignora não-arquivos
        if (!is_file($path)) continue;

        $idadeDias = ($hoje - filemtime($path)) / 86400;

        if ($idadeDias > $diasMax) {
            unlink($path);
            $apagados++;
        }
    }

    $mensagem = "✔ $apagados arquivo(s) antigo(s) excluído(s) com sucesso.";
}


// =============================================================
// Recarregar lista de arquivos (caso tenha removido)
// =============================================================
$arquivos = array_diff(scandir($dirLogs), ['.', '..']);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Logs do Controle de Pausa</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background: #0f172a;
        color: #e2e8f0;
        padding: 20px;
    }
    h1 {
        margin-bottom: 10px;
    }
    .btn-limpar {
        display: inline-block;
        background: #ef4444;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        text-decoration: none;
    }
    .msg-ok {
        background: #14532d;
        color: #a7f3d0;
        border: 1px solid #22c55e;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 15px;
    }
    .log-card {
        background: #1e293b;
        padding: 12px;
        margin-bottom: 12px;
        border-radius: 6px;
        border: 1px solid #334155;
    }
    .log-card pre {
        white-space: pre-wrap;
        word-wrap: break-word;
    }
</style>
</head>
<body>

<h1>📜 Logs do Controle de Pausa</h1>

<a class="btn-limpar" href="?limpar=1">🗑 Limpar logs com mais de 30 dias</a>

<?php if ($mensagem): ?>
    <div class="msg-ok"><?= $mensagem ?></div>
<?php endif; ?>

<hr><br>

<?php
// Se não houver logs
if (count($arquivos) === 0) {
    echo "<p>Nenhum log encontrado.</p>";
} else {

    // Ordena por data (mais recente primeiro)
    rsort($arquivos);

    foreach ($arquivos as $arq):
        $path = "$dirLogs/$arq";
        $conteudo = htmlspecialchars(file_get_contents($path));
?>
    <div class="log-card">
        <strong><?= $arq ?></strong><br>
        <small>Modificado em: <?= date('d/m/Y H:i:s', filemtime($path)) ?></small>
        <pre><?= $conteudo ?></pre>
    </div>
<?php
    endforeach;
}
?>

</body>
</html>
