<?php
require_once 'conexao.php';

// Categorias disponíveis
$categorias = [
    'topo' => 'TOPO',
    'iniciododia' => 'INÍCIO DO DIA',
    'sites' => 'SITES',
    'contato' => 'CONTATOS',
    'versoes' => 'VERSÕES',
    'telefonia' => 'TELEFONIA EVOLUX',
    'sefaz' => 'SEFAZ',
    'downloads' => 'DOWNLOADS',
    'questionarios' => 'QUESTIONÁRIOS',
    'mobile' => 'MOBILE',
    'tef' => 'TEF',
    'pix' => 'API PIX',
    'api' => 'ADICIONAIS API',
    'cobranca' => 'API COBRANÇA',
    'whatsapp' => 'API WHATSAPP',
    'cmd' => 'CMD',
    'consultaerro' => 'CONSULTA ATENDIMENTO',
    'report' => 'REPORT',
    'pdvoff' => 'PDVOFF',
    'email' => 'EMAIL',
    'backup' => 'BACKUP',
    'fiscal' => 'FISCAL',
    'tgamanuais' => 'MANUAIS',
    'dicasmovidesk' => 'DICAS MOVIDESK',
    'tgainstabilidade' => 'CONSULTA INSTABILIDADE',
    'wallpapers' => 'WALLPAPERS TGA',
    'sugestoes' => 'SUGESTOES',
    'diversos' => 'DIVERSOS',
    'rodape' => 'ENDPAGE'
];

// Links agrupados por categoria
$por_categoria = $pdo->query("SELECT categoria, COUNT(*) as total FROM links GROUP BY categoria")
                    ->fetchAll(PDO::FETCH_KEY_PAIR);

// Links mais acessados
$mais_acessados = $pdo->query("SELECT * FROM links ORDER BY acessos DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Últimos adicionados
$recentes = $pdo->query("SELECT * FROM links ORDER BY data_criacao DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Estatísticas | Painel de Links</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f2f2f2;
      padding: 30px;
      color: #333;
    }
    h1 { color: #007ced; }
    .box {
      background: white;
      border-radius: 10px;
      padding: 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      margin-bottom: 30px;
    }
    ul { list-style: none; padding-left: 0; }
    li { margin-bottom: 8px; }
    a { color: #007ced; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .tag { background: #007ced; color: white; border-radius: 5px; padding: 2px 6px; margin-left: 5px; font-size: 0.85rem; }
  </style>
</head>
<body>

<!--souza system-->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
  crossorigin="anonymous"></script>
<div style="display: flex; justify-content: center; margin: 10px 0;">
  <img alt="visitas" src="https://hits.sh/tgameajuda.com/consultasugestaophp.html.svg?color=007ced&label=visitas&labelColor=FFFFFF&logo=https%3A%2F%2Fraw.githubusercontent.com%2Fsouzaseven%2Ftgahelpme%2FDesafios%2Ficon%2520bot%2520tga.ico"/>
</div>

<h1>Estatísticas do Painel de Links</h1>

<div class="box">
  <h2>Links por Categoria</h2>
  <ul>
    <?php foreach ($categorias as $id => $nome): ?>
      <li>
        <?= $nome ?> <span class="tag"><?= $por_categoria[$id] ?? 0 ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="box">
  <h2>Mais Acessados</h2>
  <ul>
    <?php foreach ($mais_acessados as $link): ?>
      <li><a href="<?= $link['url'] ?>" target="_blank"><?= htmlspecialchars($link['titulo']) ?></a> <span class="tag"><?= $link['acessos'] ?> acesso(s)</span></li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="box">
  <h2>Últimos Adicionados</h2>
  <ul>
    <?php foreach ($recentes as $link): ?>
      <li><a href="<?= $link['url'] ?>" target="_blank"><?= htmlspecialchars($link['titulo']) ?></a> <small>(<?= date('d/m/Y H:i', strtotime($link['data_criacao'])) ?>)</small></li>
    <?php endforeach; ?>
  </ul>
</div>

</body>
</html>