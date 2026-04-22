<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/backend/conexao.php';
$csrf = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Painel de Plantão - Fim de Semana</title>

  <!-- FONTES -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- FAVICON -->
  <link rel="shortcut icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">

  <!-- CSRF -->
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

  <!-- CSS --><!---->
  <link rel="stylesheet" href="assets/css/style.css"/>


  <!-- Google Tag Manager -->
  <script>
    (function(w,d,s,l,i){
      w[l]=w[l]||[];
      w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});
      var f=d.getElementsByTagName(s)[0],
          j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
      j.async=true;
      j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
      f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-K2XFNTVZ');
  </script>

  <!-- Google Ads -->
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8542251167876044"
    crossorigin="anonymous"></script>

  <!-- Google Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-E7ZNTJSRYR"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-E7ZNTJSRYR');
  </script>

  <script async src="https://www.googletagmanager.com/gtag/js?id=G-S8EC5C2WTG"></script>
  <script>
    gtag('config', 'G-S8EC5C2WTG');
  </script>
</head>

<body>
  <!-- GTM (noscript) -->
  <noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-K2XFNTVZ"
      height="0" width="0" style="display:none;visibility:hidden"></iframe>
  </noscript>

  <div class="app">

    <!-- TOPO -->
    <header class="topbar">
      <div class="brand">
        <div class="logo">🛡️</div>
        <div class="titles">
          <h1>Painel de Plantão</h1>
          <p>Controle de plantões de fim de semana (sábado e domingo)</p>
        </div>
      </div>

      <div class="actions">
        <button class="btn small primary" id="btnCadastrarPlantao">
          + Cadastrar plantão
        </button>

        <button class="btn" id="btnRefresh">Atualizar</button>
        <button class="btn primary" id="btnNovoSuporte">Cadastrar suporte</button>
      </div>
    </header>

    <!-- INFO -->
    <section class="info">
      <div class="info-card">
        <h3>Horários deste canal</h3>
        <ul>
          <li><strong>Sábado:</strong> 13:00h às 18:00h</li>
          <li><strong>Domingo:</strong> 07:30h às 11:30h</li>
        </ul>
      </div>

      <div class="info-card">
        <h3>Regras do painel</h3>
        <ul>
          <li>Edição permitida apenas para o <strong>fim de semana atual</strong> e o <strong>próximo</strong>.</li>
          <li>A escala mensal considera apenas sábados e domingos.</li>
        </ul>
      </div>
    </section>

    <!-- RESUMO -->
    <section class="grid-3">
      <div class="card">
        <div class="card-head">
          <h2>Semana passada</h2>
        </div>
        <div class="card-body" id="cardPrev"></div>
      </div>

      <div class="card highlight">
        <div class="card-head">
          <h2>Fim de semana atual</h2>
          <button class="btn small primary" id="btnEditarAtual">Editar</button>
        </div>
        <div class="card-body" id="cardCurrent"></div>
      </div>

      <div class="card">
        <div class="card-head">
          <h2>Próximo fim de semana</h2>
          <button class="btn small primary" id="btnEditarProximo">Editar</button>
        </div>
        <div class="card-body" id="cardNext"></div>
      </div>
    </section>

    <!-- HISTÓRICO -->
    <section class="card">
      <div class="card-head">
        <h2>Histórico por período</h2>
      </div>

      <div class="card-body">
        <div class="filters">
          <div class="field">
            <label>Data início</label>
            <input type="date" id="fStart">
          </div>

          <div class="field">
            <label>Data fim</label>
            <input type="date" id="fEnd">
          </div>
<!--
          <div class="field grow">
            <label>&nbsp;</label>
            <button class="btn" id="btnFiltrar">Filtrar</button>
          </div>
        </div>
-->
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Sábado</th>
                <th>Domingo</th>
                <th>Suporte</th>
                <th>Observação</th>
              </tr>
            </thead>
            <tbody id="tbodyPeriod"></tbody>
          </table>
        </div>
      </div>
    </section>

    <footer class="footer">
      <span>Plantão • v2026.02.01</span>
    </footer>

  </div>

  <!-- MODAL SUPORTE -->
  <div class="modal-backdrop hidden" id="modalSuporte">
    <div class="modal">
      <div class="modal-head">
        <h3 id="modalSuporteTitle">Cadastrar suporte</h3>
        <button class="x" type="button" aria-label="Fechar" data-close="modalSuporte">✕</button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="supId" value="0">

        <div class="field">
          <label>Nome</label>
          <input type="text" id="supNome" placeholder="Ex: Anderson de Souza" autocomplete="off">
        </div>

        <div class="field inline">
          <label>
            <input type="checkbox" id="supAtivo" checked>
            Ativo
          </label>
        </div>

        <div class="hint">
          Cadastre o suporte e marque como <strong>Ativo</strong> para aparecer na escala.
        </div>
      </div>

      <div class="modal-foot">
        <button class="btn" type="button" data-close="modalSuporte">Cancelar</button>
        <button class="btn primary" type="button" id="btnSalvarSuporte">Salvar</button>
      </div>
    </div>
  </div>

  <!-- MODAL PLANTÃO -->
  <div class="modal-backdrop hidden" id="modalPlantao">
    <div class="modal">
      <div class="modal-head">
        <h3 id="modalPlantaoTitle">Editar plantão</h3>
        <button class="x" type="button" aria-label="Fechar" data-close="modalPlantao">✕</button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="plId" value="0">
        <input type="hidden" id="plSabado" value="">

        <!-- ✅ NOVO: permitir escolher qualquer sábado futuro -->
        <div class="field">
          <label>Sábado do plantão</label>
          <input type="date" id="plDataCustom">
        </div>
        <div class="pill" id="plRange">—</div>

        <div class="field">
          <label>Suporte</label>
          <select id="plSuporte"></select>
          <small style="color: var(--text-muted); display:block; margin-top:6px;">
            Dica: selecione <strong>Sem suporte</strong> para remover o plantão.
          </small>
        </div>

        <div class="field">
          <label>Observação (opcional)</label>
          <input type="text" id="plObs" placeholder="Ex: troca combinada, plantão parcial..." autocomplete="off">
        </div>

        <div class="hint">
          <strong>Sábado:</strong> 13:00 às 18:00 • <strong>Domingo:</strong> 07:30 às 11:30
        </div>
      </div>

      <div class="modal-foot">
        <button class="btn" type="button" data-close="modalPlantao">Cancelar</button>
        <button class="btn primary" type="button" id="btnSalvarPlantao">Salvar</button>
      </div>
    </div>
  </div>

  <div class="toast hidden" id="toast"></div>

  <!-- JS -->
<script type="module" src="assets/js/app.js"></script>

</body>
</html>
