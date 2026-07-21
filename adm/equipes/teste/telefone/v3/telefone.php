<?php
session_start();

/* =========================
   CONTROLE DE SESSÃO (5 min)
========================= */
$tempoLimite = 5 * 60; // 5 minutos

/* usuário não logado */
if (!isset($_SESSION['usuario_telefone'])) {
  header('Location: login.html');
  exit;
}

/* inicializa timestamp */
if (!isset($_SESSION['ultima_atividade'])) {
  $_SESSION['ultima_atividade'] = time();
}

/* verifica expiração */
if ((time() - $_SESSION['ultima_atividade']) > $tempoLimite) {
  session_unset();
  session_destroy();
  header('Location: login.html?expirado=1');
  exit;
}

/*
  Atualiza atividade SOMENTE em navegação normal
  (evita renovar sessão em POST automático)
*/
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $_SESSION['ultima_atividade'] = time();
}

/* =========================
   DEBUG (mantido)
========================= */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* =========================
   CONEXÃO
========================= */
require_once './conexao_page.php';

$arquivoModo = __DIR__ . '/semana_modo.json';

function normalizarLista($texto) {
  if (!is_string($texto)) {
    return [];
  }

  $texto = trim($texto);
  if ($texto === '') {
    return [];
  }

  $partes = preg_split('/[\r\n,;]+/', $texto);
  $itens = [];

  foreach ($partes as $parte) {
    $valor = trim($parte);
    if ($valor !== '') {
      $itens[] = $valor;
    }
  }

  return array_values(array_unique($itens));
}

function carregarModoSemana($arquivoModo) {
  $padrao = [
    'modo' => 'automatico',
    'manual' => [
      'subtitulo' => '',
      'aviso_resumo' => '',
      'telefone' => [],
      'chat' => [],
      'folga' => [],
      'compensacao' => []
    ]
  ];

  if (!file_exists($arquivoModo)) {
    return $padrao;
  }

  $conteudo = @file_get_contents($arquivoModo);
  if ($conteudo === false || trim($conteudo) === '') {
    return $padrao;
  }

  $json = json_decode($conteudo, true);
  if (!is_array($json)) {
    return $padrao;
  }

  $modo = isset($json['modo']) && $json['modo'] === 'manual' ? 'manual' : 'automatico';
  $manual = isset($json['manual']) && is_array($json['manual']) ? $json['manual'] : [];

  return [
    'modo' => $modo,
    'manual' => [
      'subtitulo' => isset($manual['subtitulo']) && is_string($manual['subtitulo']) ? trim($manual['subtitulo']) : '',
      'aviso_resumo' => isset($manual['aviso_resumo']) && is_string($manual['aviso_resumo']) ? trim($manual['aviso_resumo']) : '',
      'telefone' => isset($manual['telefone']) && is_array($manual['telefone']) ? array_values($manual['telefone']) : [],
      'chat' => isset($manual['chat']) && is_array($manual['chat']) ? array_values($manual['chat']) : [],
      'folga' => isset($manual['folga']) && is_array($manual['folga']) ? array_values($manual['folga']) : [],
      'compensacao' => isset($manual['compensacao']) && is_array($manual['compensacao']) ? array_values($manual['compensacao']) : []
    ]
  ];
}

function salvarModoSemana($arquivoModo, $config) {
  $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  if ($json === false) {
    return false;
  }

  return file_put_contents($arquivoModo, $json) !== false;
}


/* ============================

   CONFIGURAÇÃO DA ROTAÇÃO

============================ */

$ordem = [

  'Alex Sandro Braulio',

  'Daniel Feix',

  'Willian Pereira Reis'

];



function calcularRotacao($atual, $ordem) {

  $idx = array_search($atual, $ordem, true);

  if ($idx === false) $idx = 0;



  return [

    'anterior' => $ordem[($idx - 1 + count($ordem)) % count($ordem)],

    'atual'    => $ordem[$idx],

    'proxima'  => $ordem[($idx + 1) % count($ordem)]

  ];

}



/* ============================

   BUSCA ESTADO ATUAL

============================ */

$atualBanco = 'Daniel Feix';



$stmt = $pdo->query("SELECT atual_lider FROM rotacao_telefone WHERE id = 1");

if ($stmt && ($row = $stmt->fetch())) {

  $atualBanco = $row['atual_lider'];

}



$rotacao = calcularRotacao($atualBanco, $ordem);
$configModo = carregarModoSemana($arquivoModo);
$mensagemModo = '';



/* ============================

   PROCESSA AÇÕES

============================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {



  $novoAtual = null;



  if ($_POST['acao'] === 'avancar') {

    $novoAtual = $rotacao['proxima'];

  }



  if ($_POST['acao'] === 'manual' && !empty($_POST['atual'])) {

    $novoAtual = $_POST['atual'];

  }



  if ($novoAtual) {



    $rot = calcularRotacao($novoAtual, $ordem);



    // Atualiza líder atual

    $pdo->prepare(

      "UPDATE rotacao_telefone SET atual_lider=? WHERE id=1"

    )->execute([$novoAtual]);



    // Atualiza filas

    $stmtFila = $pdo->prepare(

      "UPDATE operadores SET fila=? WHERE lider=?"

    );



    // Quem está no telefone

    $stmtFila->execute(['Suporte Matriz', $rot['atual']]);



    // Quem está no chat/whats

    $stmtFila->execute(['Fila Matriz Chat/Whats', $rot['anterior']]);

    $stmtFila->execute(['Fila Matriz Chat/Whats', $rot['proxima']]);

    header("Location: ".$_SERVER['PHP_SELF']."#semana-telefone");

    exit;

  }

  if ($_POST['acao'] === 'salvar_modo') {
    $modoOperacao = (isset($_POST['modo_operacao']) && $_POST['modo_operacao'] === 'manual') ? 'manual' : 'automatico';

    $novoConfig = [
      'modo' => $modoOperacao,
      'manual' => [
        'subtitulo' => trim((string)($_POST['manual_subtitulo'] ?? '')),
        'aviso_resumo' => trim((string)($_POST['manual_aviso_resumo'] ?? '')),
        'telefone' => normalizarLista($_POST['manual_telefone'] ?? ''),
        'chat' => normalizarLista($_POST['manual_chat'] ?? ''),
        'folga' => normalizarLista($_POST['manual_folga'] ?? ''),
        'compensacao' => normalizarLista($_POST['manual_compensacao'] ?? '')
      ]
    ];

    if (salvarModoSemana($arquivoModo, $novoConfig)) {
      header("Location: ".$_SERVER['PHP_SELF']."#config-modo");
      exit;
    }

    $mensagemModo = 'Nao foi possivel salvar o modo. Verifique permissao de escrita do arquivo semana_modo.json.';
    $configModo = $novoConfig;
  }

}

?>

<!DOCTYPE html>

<html lang="pt-br">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Semana Telefone ADM</title>



  <!-- Favicon -->

  <link rel="shortcut icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">



  <!-- ✅ Google Tag Manager -->

<!---->

  <script>

    (function(w,d,s,l,i){

      w[l]=w[l]||[];

      w[l].push({'gtm.start': new Date().getTime(), event:'gtm.js'});

      var f=d.getElementsByTagName(s)[0],

          j=d.createElement(s),

          dl=l!='dataLayer'?'&l='+l:'';

      j.async=true;

      j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;

      f.parentNode.insertBefore(j,f);

    })(window,document,'script','dataLayer','GTM-K2XFNTVZ');

  </script>



  <!-- ✅ Google Ads -->

<!---->

  <script async

    src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"

    crossorigin="anonymous"></script>



  <!-- ✅ Google Analytics -->

  <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>

  <script>

    window.dataLayer = window.dataLayer || [];

    function gtag(){dataLayer.push(arguments);}

    gtag('js', new Date());

    gtag('config', 'G-E7ZNTJSRYR');

    gtag('config', 'G-S8EC5C2WTG');

  </script>



<link rel="stylesheet" href="../tutoriais.css">

<link rel="stylesheet" href="../painel_semana/equipes-semana.css">

</head>




<body>

<!-- RESUMO MENSAL E SEMANAL -->
<section id="resumo-mensal-semanal" style="margin-bottom:32px; padding:24px; background:rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.35); border-radius:14px;">
  <h2 style="margin-top:0; text-align:center;">📆 Resumo Mensal e Semanal das Equipes</h2>
  <p style="text-align:center; opacity:.8; margin-bottom:18px;">Previsão da escala por canal (telefone, chat, compensação) para as próximas semanas do mês.</p>
  <?php

    // Função para obter o início da semana (domingo)
    function inicioSemana($data) {
      $ts = strtotime($data);
      $dow = date('w', $ts); // 0=domingo
      return date('Y-m-d', strtotime("-".$dow." days", $ts));
    }

    // Função para obter o fim da semana (sábado)
    function fimSemana($data) {
      $ts = strtotime($data);
      $dow = date('w', $ts); // 0=domingo
      return date('Y-m-d', strtotime("+".(6-$dow)." days", $ts));
    }


    // Data de referência: hoje
    $hoje = date('Y-m-d');
    $primeiraSemana = inicioSemana($hoje);

    // Calcular a última semana do mês anterior
    $mesAtual = (int)date('m', strtotime($hoje));
    $anoAtual = (int)date('Y', strtotime($hoje));
    $mesAnterior = $mesAtual - 1;
    $anoAnterior = $anoAtual;
    if ($mesAnterior < 1) {
      $mesAnterior = 12;
      $anoAnterior--;
    }
    // Último dia do mês anterior
    $ultimoDiaMesAnterior = date('Y-m-d', strtotime($anoAnterior.'-'.$mesAnterior.'-01 +1 month -1 day'));
    $inicioUltimaSemanaAnterior = inicioSemana($ultimoDiaMesAnterior);
    $fimUltimaSemanaAnterior = fimSemana($inicioUltimaSemanaAnterior);

    $semanas = [];
    // Adiciona a última semana do mês anterior
    $semanas[] = [
      'inicio' => $inicioUltimaSemanaAnterior,
      'fim' => $fimUltimaSemanaAnterior
    ];
    // Adiciona as próximas 5 semanas (incluindo a atual)
    for ($i = 0; $i < 5; $i++) {
      $ini = date('Y-m-d', strtotime("+".($i*7)." days", strtotime($primeiraSemana)));
      $fim = fimSemana($ini);
      $semanas[] = [
        'inicio' => $ini,
        'fim' => $fim
      ];
    }

    // Equipes na ordem de rotação
    $equipes = $ordem;
    // Descobrir quem está no telefone nesta semana (base: $atualBanco)
    $idxAtual = array_search($atualBanco, $equipes);
    if ($idxAtual === false) $idxAtual = 0;

    // Descobrir qual semana contém o dia de hoje
    $semanaAtualIdx = 0;
    foreach ($semanas as $k => $sem) {
      if ($hoje >= $sem['inicio'] && $hoje <= $sem['fim']) {
        $semanaAtualIdx = $k;
        break;
      }
    }

    // Montar escala para cada semana, alinhando rotação à semana atual
    $resumoSemanas = [];
    for ($i = 0; $i < count($semanas); $i++) {
      $delta = $i - $semanaAtualIdx;
      $idxTel = ($idxAtual + $delta) % 3;
      if ($idxTel < 0) $idxTel += 3;
      $idxComp = ($idxTel + 1) % 3;
      $idxChat = ($idxTel + 2) % 3;
      $resumoSemanas[] = [
        'periodo' =>
          date('d/m', strtotime($semanas[$i]['inicio'])) . '–' . date('d/m', strtotime($semanas[$i]['fim'])),
        'telefone' => $equipes[$idxTel],
        'compensacao' => $equipes[$idxComp],
        'chat' => $equipes[$idxChat],
        'atual' => $i === $semanaAtualIdx
      ];
    }
  ?>
  <div style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; background:rgba(30,41,59,.85); color:#e2e8f0;">
      <thead>
        <tr style="background:rgba(51,65,85,.95);">
          <th style="padding:10px 8px; border-radius:8px 0 0 8px;">Semana</th>
          <th style="padding:10px 8px;">Telefone</th>
          <th style="padding:10px 8px;">Chat</th>
          <th style="padding:10px 8px; border-radius:0 8px 8px 0;">Compensação</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($resumoSemanas as $sem): ?>
        <tr style="text-align:center;<?php if ($sem['atual']) echo 'background:rgba(16,185,129,.12); font-weight:600;'; ?>">
          <td style="padding:8px 6px; border-bottom:1px solid #334155; white-space:nowrap;">
            <?= htmlspecialchars($sem['periodo']) ?>
            <?php if ($sem['atual']): ?> <span style="color:#22d3ee; font-size:12px;">(semana atual)</span><?php endif; ?>
          </td>
          <td style="padding:8px 6px; border-bottom:1px solid #334155;">
            <span style="background:linear-gradient(135deg,#047857,#22d3ee); color:#fff; padding:4px 12px; border-radius:16px; font-weight:600;"> <?= htmlspecialchars($sem['telefone']) ?> </span>
          </td>
          <td style="padding:8px 6px; border-bottom:1px solid #334155;">
            <span style="background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; padding:4px 12px; border-radius:16px; font-weight:600;"> <?= htmlspecialchars($sem['chat']) ?> </span>
          </td>
          <td style="padding:8px 6px; border-bottom:1px solid #334155;">
            <span style="background:linear-gradient(135deg,#f59e42,#fbbf24); color:#fff; padding:4px 12px; border-radius:16px; font-weight:600;"> <?= htmlspecialchars($sem['compensacao']) ?> </span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<h1>Semana Telefone – Administração</h1>

<!-- Display -->
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="ca-pub-8542251167876044"
     data-ad-slot="7622049777"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
<script>
     (adsbygoogle = window.adsbygoogle || []).push({});
</script>

<section id="semana-telefone">

  <div class="semana-telefone-resumo">



   <h3>

  <span style="font-size:22px">📅</span>

  Semana de Telefone <span style="opacity:.6">– Matriz</span>

</h3>

  <div id="modal-progresso-sinc" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#0f172a; border:1px solid rgba(148,163,184,.35); border-radius:14px; padding:28px; max-width:960px; width:95%; max-height:85vh; overflow-y:auto; text-align:left; color:#e2e8f0;">
      <h3 id="progresso-sinc-titulo" style="margin-top:0; text-align:center; color:#f1f5f9;">Sincronizando com a Evolux...</h3>

      <p id="progresso-sinc-status" style="text-align:center; opacity:.85; font-size:13px; margin:0 0 10px;">Preparando...</p>

      <div style="background:rgba(148,163,184,.25); border-radius:8px; overflow:hidden; height:10px; margin-bottom:16px;">
        <div id="progresso-sinc-barra" style="background:linear-gradient(135deg,#2563eb,#1d4ed8); height:100%; width:0%; transition:width .2s ease;"></div>
      </div>

      <div id="progresso-sinc-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px;"></div>

      <div id="progresso-sinc-fechar" style="text-align:center; margin-top:16px; display:none;">
        <button type="button" onclick="location.reload();" style="padding:10px 24px; border-radius:10px; border:none; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; font-weight:600; cursor:pointer;">
          Fechar e atualizar
        </button>
      </div>
    </div>
  </div>





    <div class="rotacao-telefone-grid-resumo">



      <div class="rotacao-telefone-item-resumo anterior">

        <span class="rotulo">Equipe Anterior</span>

        <span class="valor"><?= htmlspecialchars($rotacao['anterior']) ?></span>

        <div class="fila">Chat / Whats</div>

      </div>



      <div class="rotacao-telefone-item-resumo atual">

        <span class="rotulo">Equipe Atual (Telefone)</span>

        <span class="valor"><?= htmlspecialchars($rotacao['atual']) ?></span>

      </div>



      <div class="rotacao-telefone-item-resumo proxima">

        <span class="rotulo">Próxima Equipe</span>

        <span class="valor"><?= htmlspecialchars($rotacao['proxima']) ?></span>

        <div class="fila">Chat / Whats</div>

        <span class="extra">Semana de compensação</span>

      </div>




    <!-- Fim do grid dos cards principais -->
    </div>

    <!-- Card compacto de resumo das semanas (agora ocupa toda largura) -->
    <div id="resumo-semanas-mini" style="width:100%; max-width:900px; margin:32px auto 0 auto; background:rgba(30,41,59,.85); border-radius:14px; box-shadow:0 2px 16px rgba(0,0,0,.10); padding:18px 12px 10px 12px;">
      <div style="text-align:center; font-size:15px; font-weight:600; color:#38bdf8; margin-bottom:10px; letter-spacing:.5px;">
        <span style="font-size:18px;">📋</span> Resumo rápido das semanas
      </div>
      <table style="width:100%; font-size:13px; border-collapse:collapse;">
        <thead>
          <tr style="background:rgba(51,65,85,.95); color:#a5b4fc;">
            <th style="padding:6px 4px; border-radius:8px 0 0 8px;">Semana</th>
            <th style="padding:6px 4px;">Telefone</th>
            <th style="padding:6px 4px;">Chat</th>
            <th style="padding:6px 4px; border-radius:0 8px 8px 0;">Compensação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($resumoSemanas as $sem): ?>
          <tr style="text-align:center;<?php if ($sem['atual']) echo 'background:rgba(16,185,129,.10); font-weight:600;'; ?>">
            <td style="padding:5px 3px; border-bottom:1px solid #334155; white-space:nowrap;">
              <?= htmlspecialchars($sem['periodo']) ?>
              <?php if ($sem['atual']): ?> <span style="color:#22d3ee; font-size:11px;">(atual)</span><?php endif; ?>
            </td>
            <td style="padding:5px 3px; border-bottom:1px solid #334155;">
              <span style="background:#047857; color:#fff; padding:2px 8px; border-radius:10px; font-weight:500; font-size:12px;"> <?= htmlspecialchars($sem['telefone']) ?> </span>
            </td>
            <td style="padding:5px 3px; border-bottom:1px solid #334155;">
              <span style="background:#2563eb; color:#fff; padding:2px 8px; border-radius:10px; font-weight:500; font-size:12px;"> <?= htmlspecialchars($sem['chat']) ?> </span>
            </td>
            <td style="padding:5px 3px; border-bottom:1px solid #334155;">
              <span style="background:#f59e42; color:#fff; padding:2px 8px; border-radius:10px; font-weight:500; font-size:12px;"> <?= htmlspecialchars($sem['compensacao']) ?> </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>


    </div>

<style>

/* ===============================

   CARD BASE

=============================== */

.rotacao-telefone-item-resumo {

  border-radius: 18px;

  padding: 28px 24px;

  position: relative;

  backdrop-filter: blur(10px);

  transition: all .35s ease;

}



.rotacao-telefone-item-resumo:hover {

  transform: translateY(-4px);

}



/* ===============================

   ANTERIOR

=============================== */

.rotacao-telefone-item-resumo.anterior {

  background: linear-gradient(135deg, #1e293b, #334155);

  box-shadow: 0 0 0 1px rgba(59,130,246,.25),

              0 15px 35px rgba(0,0,0,.35);

}



/* ===============================

   ATUAL (DESTAQUE)

=============================== */

.rotacao-telefone-item-resumo.atual {

  background: linear-gradient(135deg, #064e3b, #065f46);

  box-shadow:

    0 0 0 2px rgba(34,197,94,.5),

    0 0 35px rgba(34,197,94,.45);

  animation: pulseTelefone 2.5s infinite ease-in-out;

}



@keyframes pulseTelefone {

  0% { box-shadow: 0 0 0 2px rgba(34,197,94,.4), 0 0 25px rgba(34,197,94,.3); }

  50% { box-shadow: 0 0 0 4px rgba(34,197,94,.7), 0 0 45px rgba(34,197,94,.6); }

  100% { box-shadow: 0 0 0 2px rgba(34,197,94,.4), 0 0 25px rgba(34,197,94,.3); }

}



/* ===============================

   PRÓXIMA

=============================== */

.rotacao-telefone-item-resumo.proxima {

  background: linear-gradient(135deg, #3b2f14, #5c450f);

  box-shadow:

    0 0 0 1px rgba(245,158,11,.5),

    0 15px 35px rgba(0,0,0,.35);

}



/* ===============================

   TEXTOS

=============================== */

.rotacao-telefone-item-resumo .rotulo {

  font-size: 13px;

  letter-spacing: 1px;

  opacity: .75;

  margin-bottom: 10px;

}



.rotacao-telefone-item-resumo .valor {

  font-size: 22px;

  font-weight: 600;

}



/* ===============================

   BADGES

=============================== */

.fila,

.extra {

  margin-top: 14px;

  display: inline-block;

  padding: 6px 14px;

  border-radius: 20px;

  font-size: 12px;

  font-weight: 600;

}



.fila {

  background: linear-gradient(135deg, #2563eb, #1d4ed8);

  box-shadow: 0 0 12px rgba(59,130,246,.6);

}



.extra {

  background: linear-gradient(135deg, #ef4444, #dc2626);

  box-shadow: 0 0 12px rgba(239,68,68,.6);

}



/* ===============================

   BOTÕES

=============================== */

form button {

  background: linear-gradient(135deg, #e5e7eb, #f9fafb);

  border: none;

  padding: 10px 18px;

  border-radius: 10px;

  font-weight: 600;

  cursor: pointer;

  transition: all .25s ease;

}



form button:hover {

  transform: translateY(-2px);

  box-shadow: 0 10px 20px rgba(0,0,0,.25);

}



form button:active {

  transform: scale(.97);

}



.btn-mudar-equipe {

  display: inline-block;

  padding: 10px 18px;

  background: linear-gradient(135deg, #2563eb, #1d4ed8);

  color: #fff;

  text-decoration: none;

  border-radius: 10px;

  font-weight: 600;

  transition: all .25s ease;

}



.btn-mudar-equipe:hover {

  transform: translateY(-2px);

  box-shadow: 0 10px 20px rgba(37,99,235,.4);

}



</style>



    <!-- CONTROLES -->

    <div style="margin-top:20px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">



      <form method="post" onsubmit="return abrirModalSincronizacao(this);">

        <input type="hidden" name="acao" value="avancar">

        <button type="submit">⏭️ Avançar rotação</button>

      </form>

      <form method="post" onsubmit="return abrirModalSincronizacao(this);">

        <input type="hidden" name="acao" value="avancar">

        <button type="submit">⏭️ Avançar auto (API/local)</button>

      </form>


      <form method="post" style="display:flex; gap:6px;" onsubmit="return abrirModalSincronizacao(this);">

        <input type="hidden" name="acao" value="manual">

        <select name="atual" required>

          <?php foreach ($ordem as $nome): ?>

            <option value="<?= $nome ?>" <?= $rotacao['atual'] === $nome ? 'selected' : '' ?>>

              <?= $nome ?>

            </option>

          <?php endforeach; ?>

        </select>

        <button type="submit">✍️ Definir manual</button>

      </form>

      <div id="modal-sinc-evolux" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#0f172a; border:1px solid rgba(148,163,184,.35); border-radius:14px; padding:28px; max-width:440px; width:90%; text-align:center;">
          <h3 style="margin-top:0;">Como deseja aplicar esta mudança?</h3>
          <p style="opacity:.8; font-size:14px;">
            Você pode mudar apenas a fila/líder da semana aqui no painel (como hoje), ou também sincronizar cada operador do líder na Evolux via API, movendo-os de fila de verdade no sistema de telefonia.
          </p>
          <div style="display:flex; flex-direction:column; gap:10px; margin-top:20px;">
            <button type="button" onclick="confirmarSincronizacao(false)" style="padding:12px; border-radius:10px; border:none; background:linear-gradient(135deg,#334155,#1e293b); color:#e2e8f0; font-weight:600; cursor:pointer;">
              Só como está hoje (apenas local)
            </button>
            <button type="button" onclick="confirmarSincronizacao(true)" style="padding:12px; border-radius:10px; border:none; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; font-weight:600; cursor:pointer;">
              Local + sincronizar operadores na Evolux
            </button>
            <button type="button" onclick="fecharModalSincronizacao()" style="padding:8px; border:none; background:transparent; color:#94a3b8; cursor:pointer; font-size:13px;">
              Cancelar
            </button>
          </div>
        </div>
      </div>

      <script>
        let formPendenteSincronizacao = null;

        function abrirModalSincronizacao(form) {
          formPendenteSincronizacao = form;
          document.getElementById('modal-sinc-evolux').style.display = 'flex';
          return false;
        }

        function fecharModalSincronizacao() {
          formPendenteSincronizacao = null;
          document.getElementById('modal-sinc-evolux').style.display = 'none';
        }

        function confirmarSincronizacao(sincronizar) {
          if (!formPendenteSincronizacao) return;
          const form = formPendenteSincronizacao;
          formPendenteSincronizacao = null;
          document.getElementById('modal-sinc-evolux').style.display = 'none';

          if (!sincronizar) {
            form.submit();
            return;
          }

          executarSincronizacaoComProgresso(form);
        }

        function iconeStatus(status) {
          return status === 'sucesso' ? '✅' : (status === 'ignorado' ? '⚪' : '❌');
        }

        function criarCardLider(liderNome, filaNome) {
          const div = document.createElement('div');
          div.style.cssText = 'background:rgba(15,23,42,.6); border:1px solid rgba(148,163,184,.25); border-radius:10px; padding:10px 14px;';
          div.innerHTML =
            '<div style="font-weight:700; font-size:14px; margin-bottom:6px; display:flex; justify-content:space-between; gap:10px; color:#f8fafc;">' +
              '<span>' + liderNome + '</span>' +
              '<span style="opacity:.85; font-weight:500; color:#93c5fd;">' + filaNome + '</span>' +
            '</div>' +
            '<ul style="list-style:none; margin:0; padding:0; font-size:13px; color:#e2e8f0;"></ul>';
          return div;
        }

        async function executarSincronizacaoComProgresso(form) {
          const modal   = document.getElementById('modal-progresso-sinc');
          const barra   = document.getElementById('progresso-sinc-barra');
          const status  = document.getElementById('progresso-sinc-status');
          const grid    = document.getElementById('progresso-sinc-grid');
          const fechar  = document.getElementById('progresso-sinc-fechar');
          const titulo  = document.getElementById('progresso-sinc-titulo');

          grid.innerHTML = '';
          fechar.style.display = 'none';
          titulo.textContent = 'Sincronizando com a Evolux...';
          status.textContent = 'Aplicando localmente...';
          barra.style.width = '0%';
          modal.style.display = 'flex';

          let prep;
          try {
            const r = await fetch('preparar_sincronizacao.php', { method: 'POST', body: new FormData(form) });
            prep = await r.json();
          } catch (e) {
            prep = { success: false, erro: 'Falha de rede ao aplicar localmente.' };
          }

          if (!prep.success) {
            status.textContent = prep.erro || 'Erro ao aplicar a rotação.';
            fechar.style.display = 'block';
            return;
          }

          status.textContent = 'Buscando filas da Evolux...';
          let filas = [];
          try {
            const rf = await fetch('evolux_filas.php');
            const jf = await rf.json();
            filas = jf.filas || [];
          } catch (e) {}

          function encontrarQueueId(nome) {
            const alvo = (nome || '').trim().toLowerCase();
            const achada = filas.find(f => (f.name || '').trim().toLowerCase() === alvo);
            return achada ? achada.id : null;
          }

          status.textContent = 'Carregando operadores dos líderes...';
          const listasPorLider = {};
          const todos = [];

          for (const grupo of prep.grupos) {
            const card = criarCardLider(grupo.lider, grupo.fila);
            grid.appendChild(card);
            listasPorLider[grupo.lider] = card.querySelector('ul');

            let ops = [];
            try {
              const ro = await fetch('operadores_por_lider.php?lider=' + encodeURIComponent(grupo.lider));
              const jo = await ro.json();
              ops = jo.operadores || [];
            } catch (e) {}

            ops.forEach(op => todos.push({ ...op, lider: grupo.lider, fila: grupo.fila }));
          }

          const total = todos.length;
          let sucesso = 0, falha = 0, ignorados = 0;

          for (let i = 0; i < total; i++) {
            const op = todos[i];
            status.textContent = `Sincronizando ${op.nome} — líder ${op.lider} (${i + 1}/${total})`;

            const li = document.createElement('li');
            li.style.cssText = 'padding:3px 0; border-bottom:1px solid rgba(148,163,184,.12);';
            li.textContent = `⏳ ${op.nome}`;
            listasPorLider[op.lider].appendChild(li);

            let statusFinal;

            if (!op.evolux_agent_id) {
              statusFinal = 'ignorado';
              ignorados++;
            } else {
              const queueId = encontrarQueueId(op.fila);
              if (queueId === null) {
                statusFinal = 'falha';
                falha++;
              } else {
                const fd = new FormData();
                fd.append('operador_id', op.id);
                fd.append('evolux_agent_id', op.evolux_agent_id);
                fd.append('queue_id', queueId);
                fd.append('queue_nome', op.fila);

                try {
                  const rr = await fetch('sincronizar_operador.php', { method: 'POST', body: fd });
                  const jr = await rr.json();
                  if (jr.success) {
                    statusFinal = 'sucesso';
                    sucesso++;
                  } else {
                    statusFinal = 'falha';
                    falha++;
                    console.warn('Falha ao sincronizar', op.nome, jr);
                  }
                } catch (e) {
                  statusFinal = 'falha';
                  falha++;
                  console.warn('Falha ao sincronizar (rede)', op.nome, e);
                }
              }
            }

            li.textContent = iconeStatus(statusFinal) + ' ' + op.nome;

            const pct = total ? Math.round(((i + 1) / total) * 100) : 100;
            barra.style.width = pct + '%';
          }

          titulo.textContent = 'Sincronização concluída';
          status.textContent = `Sincronizado: ${sucesso} operador(es), ${falha} falha(s), ${ignorados} sem agente Evolux.`;
          fechar.style.display = 'block';
        }
      </script>

<a 

  href="https://tgameajuda.com/telefonia-evolux/filas_agentes/login.php#google_vignette"

  class="btn-mudar-equipe"

  target="_blank"

  rel="noopener noreferrer"

>

  🔄 Mudar equipes e filas

</a>




<button class="btn-logout" onclick="window.location.href='logout.php'">
  Sair
</button>
<style>
.btn-logout {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: #ffffff;
  border: none;
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.25s ease;
  box-shadow: 0 6px 15px rgba(239, 68, 68, 0.35);
}

.btn-logout:hover {
  transform: translateY(-1px);
  box-shadow: 0 10px 25px rgba(239, 68, 68, 0.55);
}

.btn-logout:active {
  transform: translateY(0);
  box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
}

.btn-logout:focus {
  outline: none;
}
.header-actions {
  display: flex;
  justify-content: flex-end;
  padding: 10px 20px;
}

</style>
    </div>

    <div id="config-modo" style="margin-top:20px; padding:18px; background:rgba(15,23,42,.55); border:1px solid rgba(148,163,184,.35); border-radius:14px;">
      <h3 style="margin:0 0 12px 0; text-align:center;">Configurar exibicao no index (automatico/manual)</h3>

      <?php
        $modoAtual = $configModo['modo'] ?? 'automatico';
        $isManual  = $modoAtual === 'manual';
      ?>
      <div style="text-align:center; margin-bottom:16px;">
        <span style="
          display:inline-block; padding:8px 20px; border-radius:20px;
          font-weight:700; font-size:13px; letter-spacing:.5px;
          background:<?= $isManual
            ? 'linear-gradient(135deg,#7c3aed,#6d28d9)'
            : 'linear-gradient(135deg,#065f46,#047857)' ?>;
          box-shadow:0 0 14px <?= $isManual
            ? 'rgba(124,58,237,.55)'
            : 'rgba(5,150,105,.55)' ?>;">
          <?= $isManual ? '🟡 MODO ATUAL: MANUAL' : '🟢 MODO ATUAL: AUTOMÁTICO' ?>
        </span>
      </div>

      <?php if (!empty($mensagemModo)): ?>
        <p style="margin:8px 0 14px; color:#fecaca; text-align:center;"><?= htmlspecialchars($mensagemModo) ?></p>
      <?php endif; ?>

      <form method="post" style="display:grid; gap:12px;">
        <input type="hidden" name="acao" value="salvar_modo">

        <label style="font-weight:600;">Modo de operacao</label>
        <select name="modo_operacao" style="padding:10px; border-radius:8px; border:1px solid #334155; background:#0f172a; color:#e2e8f0;">
          <option value="automatico" <?= ($configModo['modo'] ?? 'automatico') === 'automatico' ? 'selected' : '' ?>>Automatico (segue rotacao)</option>
          <option value="manual" <?= ($configModo['modo'] ?? 'automatico') === 'manual' ? 'selected' : '' ?>>Manual (define por tipo)</option>
        </select>

        <div>
          <label style="display:block; margin-bottom:6px; font-weight:600;">Subtitulo (ex.: Semana de 20/04/2026 a 26/04/2026)</label>
          <input type="text" name="manual_subtitulo" value="<?= htmlspecialchars($configModo['manual']['subtitulo'] ?? '') ?>" style="width:100%; border-radius:8px; padding:10px; border:1px solid #334155; background:#0f172a; color:#e2e8f0;" />
        </div>

        <div>
          <label style="display:block; margin-bottom:6px; font-weight:600;">Aviso resumo</label>
          <textarea name="manual_aviso_resumo" rows="5" style="width:100%; border-radius:8px; padding:10px; border:1px solid #334155; background:#0f172a; color:#e2e8f0;"><?= htmlspecialchars($configModo['manual']['aviso_resumo'] ?? '') ?></textarea>
        </div>

        <div style="display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
          <div>
            <label style="display:block; margin-bottom:6px; font-weight:600;">Telefone</label>
            <textarea name="manual_telefone" rows="3" style="width:100%; border-radius:8px; padding:10px; border:1px solid #334155; background:#0f172a; color:#e2e8f0;"><?= htmlspecialchars(implode("\n", $configModo['manual']['telefone'] ?? [])) ?></textarea>
          </div>

          <div>
            <label style="display:block; margin-bottom:6px; font-weight:600;">Chat / Whats</label>
            <textarea name="manual_chat" rows="3" style="width:100%; border-radius:8px; padding:10px; border:1px solid #334155; background:#0f172a; color:#e2e8f0;"><?= htmlspecialchars(implode("\n", $configModo['manual']['chat'] ?? [])) ?></textarea>
          </div>

          <div>
            <label style="display:block; margin-bottom:6px; font-weight:600;">Folga</label>
            <textarea name="manual_folga" rows="3" style="width:100%; border-radius:8px; padding:10px; border:1px solid #334155; background:#0f172a; color:#e2e8f0;"><?= htmlspecialchars(implode("\n", $configModo['manual']['folga'] ?? [])) ?></textarea>
          </div>

          <div>
            <label style="display:block; margin-bottom:6px; font-weight:600;">Compensacao</label>
            <textarea name="manual_compensacao" rows="3" style="width:100%; border-radius:8px; padding:10px; border:1px solid #334155; background:#0f172a; color:#e2e8f0;"><?= htmlspecialchars(implode("\n", $configModo['manual']['compensacao'] ?? [])) ?></textarea>
          </div>
        </div>

        <small style="opacity:.8;">Dica: voce pode separar nomes por linha, virgula ou ponto e virgula.</small>
        <button type="submit" style="justify-self:center;">Salvar configuracao</button>
      </form>
    </div>



  </div>


</section>

<!-- Display -->
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="ca-pub-8542251167876044"
     data-ad-slot="7622049777"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
<script>
     (adsbygoogle = window.adsbygoogle || []).push({});
</script>

</body>

</html>