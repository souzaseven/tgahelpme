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



      <form method="post">

        <input type="hidden" name="acao" value="avancar">

        <button type="submit">⏭️ Avançar rotação</button>

      </form>



      <form method="post" style="display:flex; gap:6px;">

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

