<?php
/**
 * Estrutura do Projeto - Visualizador
 * Autor: Anderson de Souza
 * Projeto: Plataforma de Vagas
 */

$rootPath = realpath(__DIR__ . '/../'); // Ajuste se necessário

$ignore = [
    'vendor',
    'node_modules',
    '.git',
    'uploads','backups'
];

function listarArquivos($dir, $ignore = []) {
    $files = scandir($dir);
    echo "<ul>";
    foreach ($files as $file) {

        if ($file === '.' || $file === '..') continue;
        if (in_array($file, $ignore)) continue;

        $path = $dir . DIRECTORY_SEPARATOR . $file;
        $isDir = is_dir($path);

        echo "<li>";
        if ($isDir) {
            echo "<span class='folder' onclick='toggle(this)'>📁 $file</span>";
            listarArquivos($path, $ignore);
        } else {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            echo "<span class='file file-$ext'>📄 $file</span>";
        }
        echo "</li>";
    }
    echo "</ul>";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Estrutura do Projeto</title>

<!--icone da pagina-->
<link rel="icon" href="http://tgameajuda.com/Projetos/tgacarreiras/img/icone_logo.ico" type="image/x-icon">


<!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044" crossorigin="anonymous"></script>

    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-S8EC5C2WTG');
    </script>
<style>
body {
    background:#0f172a;
    color:#e2e8f0;
    font-family: 'Inter', sans-serif;
    padding:40px;
}

h1 {
    margin-bottom:20px;
}

ul {
    list-style:none;
    padding-left:20px;
    margin:4px 0;
}

li {
    margin:4px 0;
}

.folder {
    cursor:pointer;
    font-weight:600;
    color:#38bdf8;
}

.file {
    color:#cbd5e1;
}

.file-php { color:#facc15; }
.file-js  { color:#22d3ee; }
.file-css { color:#f472b6; }
.file-html{ color:#fb7185; }

ul ul {
    display:none;
}

.open > ul {
    display:block;
}
</style>
</head>
<body>

<h1>📂 Estrutura do Projeto</h1>

<?php listarArquivos($rootPath, $ignore); ?>

<script>
function toggle(el){
    let li = el.parentElement;
    li.classList.toggle('open');
}
</script>

</body>
</html>
