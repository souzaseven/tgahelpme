<?php
// Realtime carregado via AJAX (JS chama api-handler.php)
?>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">⚡ Monitoramento em Tempo Real</h2>
    <div style="display:flex; gap:1rem; align-items:center;">
      <select class="form-control" style="width:auto;" id="refreshTime">
        <option value="5000" selected>5 segundos</option>
        <option value="10000">10 segundos</option>
        <option value="30000">30 segundos</option>
        <option value="0">Sem refresh</option>
      </select>
      <button class="btn btn-sm btn-primary" onclick="carregarRealtime()">
        🔄 Atualizar Agora
      </button>
    </div>
  </div>
</div>

<div class="dashboard-grid" style="margin-top:1.5rem;">
  <div class="card stats-card">
    <div class="stats-label">👤 Agentes</div>
    <div class="stats-value" id="totalAgentes">-</div>
    <div class="stats-trend"><span id="agentesOnline" style="color:var(--success);">-</span> online</div>
  </div>

  <div class="card stats-card">
    <div class="stats-label">📞 Chamadas</div>
    <div class="stats-value" id="totalChamadas">-</div>
    <div class="stats-trend">em andamento</div>
  </div>

  <div class="card stats-card">
    <div class="stats-label">👥 Filas</div>
    <div class="stats-value" id="totalFilas">-</div>
    <div class="stats-trend">ativas</div>
  </div>

  <div class="card stats-card">
    <div class="stats-label">📡 Canais</div>
    <div class="stats-value" id="totalCanais">-</div>
    <div class="stats-trend">disponíveis</div>
  </div>
</div>

<div class="card" style="margin-top:1.5rem;">
  <div class="card-header" style="border-bottom:none; padding-bottom:0;">
    <ul class="nav-list" style="display:flex; gap:.5rem; list-style:none; padding:0; margin:0; border-bottom:2px solid var(--border);">
      <li><a href="#" class="nav-link active" onclick="trocarTab(event,'agentes')">👤 Agentes</a></li>
      <li><a href="#" class="nav-link" onclick="trocarTab(event,'chamadas')">📞 Chamadas</a></li>
      <li><a href="#" class="nav-link" onclick="trocarTab(event,'filas')">👥 Filas</a></li>
      <li><a href="#" class="nav-link" onclick="trocarTab(event,'canais')">📡 Canais</a></li>
    </ul>
  </div>

  <div class="card-body">

    <div id="tab-agentes" class="tab-content active">
      <h3 style="margin-bottom:1rem;">👤 Agentes em Tempo Real</h3>
      <div class="table-responsive">
        <table id="tabelaAgentesRealtime">
          <thead>
            <tr>
              <th>Nome</th>
              <th>Login</th>
              <th>Status</th>
              <th>Posição</th>
              <th>Tempo</th>
            </tr>
          </thead>
          <tbody id="agentesRealtimeBody">
            <tr><td colspan="5" class="text-center" style="padding:2rem; color:var(--text-muted);">Carregando...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="tab-chamadas" class="tab-content" style="display:none;">
      <h3 style="margin-bottom:1rem;">📞 Chamadas em Andamento</h3>
      <div class="table-responsive">
        <table id="tabelaChamadasRealtime">
          <thead>
            <tr>
              <th>UUID</th>
              <th>Número</th>
              <th>Agente</th>
              <th>Estado</th>
              <th>Início</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody id="chamadasRealtimeBody">
            <tr><td colspan="6" class="text-center" style="padding:2rem; color:var(--text-muted);">Carregando...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="tab-filas" class="tab-content" style="display:none;">
      <h3 style="margin-bottom:1rem;">👥 Filas em Tempo Real</h3>
      <div id="filasContainer" class="dashboard-grid">
        <div class="card" style="padding:2rem; text-align:center; grid-column:1 / -1; color:var(--text-muted);">Carregando...</div>
      </div>
    </div>

    <div id="tab-canais" class="tab-content" style="display:none;">
      <h3 style="margin-bottom:1rem;">📡 Status dos Canais</h3>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>Canal</th>
              <th>Status</th>
              <th>Tipo</th>
              <th>Contexto</th>
            </tr>
          </thead>
          <tbody id="canaisRealtimeBody">
            <tr><td colspan="4" class="text-center" style="padding:2rem; color:var(--text-muted);">Carregando...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<style>
.nav-link{color:var(--text-secondary); text-decoration:none; padding:.75rem 1.5rem; display:block; border-bottom:3px solid transparent; transition:.2s}
.nav-link:hover{color:var(--text-primary)}
.nav-link.active{color:var(--primary)!important; border-bottom-color:var(--primary)!important}
.tab-content{display:none}
.tab-content.active{display:block}
</style>

<script>
let realtimeInterval = null;

function trocarTab(event, tabName){
  event.preventDefault();
  document.querySelectorAll('.nav-link').forEach(l=>l.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
  event.target.closest('.nav-link').classList.add('active');
  document.getElementById('tab-'+tabName).classList.add('active');
}

document.addEventListener('DOMContentLoaded', () => {
  const refreshSelect = document.getElementById('refreshTime');

  function iniciarAutoRefresh(ms){
    if (realtimeInterval){ clearInterval(realtimeInterval); realtimeInterval=null; }
    if (ms > 0) realtimeInterval = setInterval(carregarRealtime, ms);
  }

  refreshSelect.addEventListener('change', () => {
    const ms = parseInt(refreshSelect.value || '0', 10);
    iniciarAutoRefresh(ms);
    if (typeof showNotification === 'function'){
      showNotification(ms>0 ? `Auto-refresh: ${ms/1000}s` : 'Auto-refresh desativado', 'info');
    }
  });

  carregarRealtime();
  iniciarAutoRefresh(parseInt(refreshSelect.value || '5000', 10));
});

async function carregarRealtime(){
  try{
    const [agentesRes, chamadasRes, filasRes, canaisRes] = await Promise.all([
      api.get('api-handler.php?action=realtime_agentes').catch(() => ({success:false, data:[]})),
      api.get('api-handler.php?action=realtime_chamadas').catch(() => ({success:false, data:[]})),
      api.get('api-handler.php?action=realtime_filas').catch(() => ({success:false, data:[]})),
      api.get('api-handler.php?action=realtime_canais').catch(() => ({success:false, data:[]})),
    ]);

    const agentes  = agentesRes?.data  || [];
    const chamadas = chamadasRes?.data || [];
    const filas    = filasRes?.data    || [];
    const canais   = canaisRes?.data   || [];

    document.getElementById('totalAgentes').textContent   = agentes.length;
    document.getElementById('agentesOnline').textContent  = agentes.filter(a => a.logged === true).length;
    document.getElementById('totalChamadas').textContent  = chamadas.length;
    document.getElementById('totalFilas').textContent     = filas.length;
    document.getElementById('totalCanais').textContent    = canais.length;

    atualizarTabelaAgentes(agentes);
    atualizarTabelaChamadas(chamadas);
    atualizarFilas(filas);
    atualizarCanais(canais);

  } catch(e){
    console.error(e);
    if (typeof showNotification === 'function'){
      showNotification('Erro ao atualizar realtime', 'danger');
    }
  }
}

function atualizarTabelaAgentes(agentes){
  const tbody = document.getElementById('agentesRealtimeBody');
  if (!agentes.length){
    tbody.innerHTML = `<tr><td colspan="5" class="text-center" style="padding:2rem; color:var(--text-muted);">Nenhum agente</td></tr>`;
    return;
  }
  tbody.innerHTML = agentes.map(a=>`
    <tr>
      <td><strong>${a.name || '-'}</strong></td>
      <td>${a.login || '-'}</td>
      <td><span class="badge badge-${a.logged ? 'success' : 'secondary'}">${a.logged ? '🟢 Online' : '⚪ Offline'}</span></td>
      <td>${a.position || '-'}</td>
      <td>${a.login_start || '-'}</td>
    </tr>
  `).join('');
}

function atualizarTabelaChamadas(chamadas){
  const tbody = document.getElementById('chamadasRealtimeBody');
  if (!chamadas.length){
    tbody.innerHTML = `<tr><td colspan="6" class="text-center" style="padding:2rem; color:var(--text-muted);">Nenhuma chamada ativa</td></tr>`;
    return;
  }
  tbody.innerHTML = chamadas.map(c=>`
    <tr>
      <td><strong>${c.uuid || c.id || '-'}</strong></td>
      <td>${c.subscriber?.number || c.caller || '-'}</td>
      <td>${c.agent_id || c.agent || '-'}</td>
      <td><span class="badge badge-info">${c.state || c.status || 'Em andamento'}</span></td>
      <td>${c.start_time || c.inicio || '-'}</td>
      <td>
        <button class="btn btn-sm btn-danger" onclick="desligarChamada('${c.uuid || c.id || ''}')" title="Desligar chamada">📵</button>
      </td>
    </tr>
  `).join('');
}

function atualizarFilas(filas){
  const container = document.getElementById('filasContainer');
  if (!filas.length){
    container.innerHTML = `<div class="card" style="padding:2rem; text-align:center; grid-column:1 / -1; color:var(--text-muted);">Nenhuma fila ativa</div>`;
    return;
  }
  container.innerHTML = filas.map(f=>`
    <div class="card" style="padding:1.5rem;">
      <h4 style="margin-bottom:1rem;">${f.name || ('Fila ' + (f.id||''))}</h4>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div>
          <div style="color:var(--text-secondary); font-size:.875rem;">Aguardando</div>
          <div style="font-size:1.5rem; font-weight:600; color:var(--warning);">${f.calls_waiting || f.waiting || 0}</div>
        </div>
        <div>
          <div style="color:var(--text-secondary); font-size:.875rem;">Agentes Logados</div>
          <div style="font-size:1.5rem; font-weight:600; color:var(--success);">${f.logged_agents || f.agents || 0}</div>
        </div>
        <div>
          <div style="color:var(--text-secondary); font-size:.875rem;">Tempo Médio</div>
          <div style="font-size:1.25rem; font-weight:600;">${f.avg_time || '0s'}</div>
        </div>
        <div>
          <div style="color:var(--text-secondary); font-size:.875rem;">Abandonadas</div>
          <div style="font-size:1.25rem; font-weight:600; color:var(--danger);">${f.abandoned || 0}</div>
        </div>
      </div>
    </div>
  `).join('');
}

function atualizarCanais(canais){
  const tbody = document.getElementById('canaisRealtimeBody');
  if (!canais.length){
    tbody.innerHTML = `<tr><td colspan="4" class="text-center" style="padding:2rem; color:var(--text-muted);">Nenhum canal encontrado</td></tr>`;
    return;
  }
  tbody.innerHTML = canais.map(c=>`
    <tr>
      <td><strong>${c.channel || '-'}</strong></td>
      <td><span class="badge badge-${c.status === 'up' ? 'success' : c.status === 'down' ? 'danger' : 'secondary'}">${(c.status || 'unknown').toUpperCase()}</span></td>
      <td>${c.type || '-'}</td>
      <td>${c.context || '-'}</td>
    </tr>
  `).join('');
}

async function desligarChamada(uuid){
  if (!uuid) return;
  if (!confirm('Tem certeza que deseja desligar esta chamada?')) return;

  try{
    if (typeof showNotification === 'function') showNotification('Desligando chamada...', 'info');
    const res = await api.post('api-handler.php?action=desligar_chamada', { uuid });
    if (res.success){
      if (typeof showNotification === 'function') showNotification('Chamada desligada!', 'success');
      setTimeout(carregarRealtime, 1000);
    } else {
      if (typeof showNotification === 'function') showNotification(res.error || 'Erro ao desligar', 'danger');
    }
  } catch(e){
    console.error(e);
    if (typeof showNotification === 'function') showNotification('Erro ao desligar chamada', 'danger');
  }
}
</script>
