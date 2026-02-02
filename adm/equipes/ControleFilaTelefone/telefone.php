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

