<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

/* Verificação de acesso do site (arquivo na raiz de tgameajuda.com).
   Roda ANTES do bootstrap: ele abre a sessão padrão e o bootstrap a reaproveita. */
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
include_once $_SERVER['DOCUMENT_ROOT'] . '/verifica_acesso.php';

require_once __DIR__ . '/auth_guard.php';
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- FAVICON -->
  <link rel="shortcut icon" href="https://tgameajuda.com/img/principal/bot-tga.webp" type="image/x-icon">

  <!-- CSRF -->
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

  <!-- CSS --><!---->
  <link rel="stylesheet" href="assets/css/style.css"/>

</head>

<body>
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
        <button class="btn primary" id="btnCadastrarPlantao">
          + Cadastrar plantão
        </button>

        <button class="btn cyan" id="btnRefresh">Atualizar</button>
        <button class="btn success" id="btnNovoSuporte">Cadastrar suporte</button>
        <a href="publico/index.html" target="_blank" class="btn accent" style="text-decoration:none;">👁 Página pública</a>
        <a href="logout.php" class="btn danger" style="text-decoration:none;">Sair</a>
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

    <!-- COLABORADORES -->
    <section class="card" style="margin-bottom:32px">
      <div class="card-head card-head--toggle" id="toggleColaboradores">
        <h2>Colaboradores</h2>
        <span class="toggle-chevron">▾</span>
      </div>
      <div class="card-body card-body--collapsible" id="cardColaboradores">
        <p class="empty-stats">Carregando...</p>
      </div>
    </section>

    <!-- ESTATÍSTICAS -->
    <section class="card" style="margin-bottom:32px">
      <div class="card-head">
        <h2>Plantões por colaborador</h2>
        <div class="card-actions">
          <input type="month" id="statsMonth" class="input-month">
        </div>
      </div>
      <div class="card-body" id="cardStats">
        <p class="empty-stats">Carregando...</p>
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
        </div>

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

    <!-- RANKING DE PLANTÕES -->
    <section class="card" style="margin-top:32px">
      <div class="card-head">
        <h2>Ranking de plantões</h2>
        <div class="card-actions">
          <select id="rankingRange" class="input-month">
            <option value="all">Todo o período</option>
          </select>
        </div>
      </div>

      <div class="card-body" id="cardRanking">
        <p class="empty-stats">Carregando...</p>
      </div>
    </section>

    <footer class="footer">
      <span>Plantão • v2026.02.01</span>
    </footer>

  </div>

  <!-- MODAL SUPORTE -->
  <div class="modal-backdrop hidden" id="modalSuporte">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalSuporteTitle">
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
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalPlantaoTitle">
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
