/**
 * app.js
 * Ponto de entrada: liga eventos da UI aos módulos de auth/api/storage.
 */

document.addEventListener('DOMContentLoaded', () => {

  // ---------- Restaura sessão (se a aba ainda tiver token em sessionStorage) ----------
  if (TgaStorage.hasToken()) {
    TgaUi.setSessionAuthenticated(TgaStorage.getToken());
  }

  // ---------- Teste de conectividade ----------
  TgaApi.testConnectivity().then(result => TgaUi.setConnStatus(result.online));

  // ---------- Expiração do token: atualiza o texto e expira proativamente ----------
  // (sem esperar a próxima requisição levar um 401 pra descobrir que venceu)
  setInterval(() => TgaUi.checkTokenExpiry(), 30000);

  // ---------- Formulário de login ----------
  const loginForm = document.getElementById('loginForm');
  const btnLogin = document.getElementById('btnLogin');
  const loginMeta = document.getElementById('loginMeta');

  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;

    if (!username || !password) return;

    btnLogin.disabled = true;
    btnLogin.textContent = 'Autenticando…';
    loginMeta.textContent = '';
    document.getElementById('loginFeedback').classList.remove('hidden');
    loginForm.classList.add('is-loading');
    document.getElementById('username').disabled = true;
    document.getElementById('password').disabled = true;

    const t0 = performance.now();
    await TgaAuth.login(username, password);
    const elapsed = Math.round(performance.now() - t0);

    loginForm.classList.remove('is-loading');
    document.getElementById('username').disabled = false;
    document.getElementById('password').disabled = false;

    loginMeta.textContent = `Última tentativa às ${new Date().toLocaleTimeString('pt-BR')} (${elapsed} ms)`;
    btnLogin.disabled = false;
    btnLogin.textContent = 'Autenticar';

    // Nunca deixar a senha residir no campo/DOM além do necessário.
    document.getElementById('password').value = '';
  });

  // ---------- Sessão da API: mostrar / ocultar / copiar / limpar ----------
  const tokenDisplay = document.getElementById('tokenDisplay');

  document.getElementById('btnShowToken').addEventListener('click', (e) => {
    const revealed = tokenDisplay.dataset.revealed === 'true';
    if (revealed) {
      tokenDisplay.textContent = TgaUi.maskToken(tokenDisplay.dataset.full);
      tokenDisplay.dataset.revealed = 'false';
      e.target.textContent = 'Mostrar';
    } else {
      tokenDisplay.textContent = tokenDisplay.dataset.full;
      tokenDisplay.dataset.revealed = 'true';
      e.target.textContent = 'Ocultar';
    }
  });

  document.getElementById('btnCopyToken').addEventListener('click', async () => {
    const full = tokenDisplay.dataset.full;
    if (!full) return;
    await navigator.clipboard.writeText(full);
    flashButton('btnCopyToken', 'Copiado!');
  });

  document.getElementById('btnClearSession').addEventListener('click', () => {
    TgaStorage.clearToken();
    TgaUi.setSessionCleared();
    document.getElementById('loginFeedback').classList.add('hidden');
  });

  document.getElementById('btnReauth').addEventListener('click', () => {
    document.getElementById('loginCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.getElementById('username').focus();
  });

  // ---------- Explorador de API — componente genérico (Fase 4) ----------
  TgaExplorer.init();

  document.getElementById('sidebarAuthItem').addEventListener('click', () => {
    document.getElementById('loginCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  // ---------- Copiar resposta JSON ----------
  document.getElementById('btnCopyResponse').addEventListener('click', async (e) => {
    const payload = e.target.dataset.payload;
    if (!payload) return;
    await navigator.clipboard.writeText(payload);
    flashButton('btnCopyResponse', 'Copiado!');
  });

  // ---------- Response Viewer: abas JSON / Tabela / Raw + busca ----------
  document.getElementById('responseViewTabs').addEventListener('click', (e) => {
    const tab = e.target.closest('.view-tab');
    if (!tab) return;
    TgaUi.setResponseView(tab.dataset.view);
  });

  document.getElementById('responseSearch').addEventListener('input', (e) => {
    TgaUi.filterStructuredView(e.target.value);
  });

  document.getElementById('btnExportCsv').addEventListener('click', () => {
    TgaUi.exportTableCsv();
  });

  // ---------- Expandir Response em tela cheia ----------
  // Move o bloco #responseExpandTarget (abas + busca + tabela/ficha) para
  // dentro do modal e depois de volta pro lugar original — o mesmo elemento
  // e os mesmos listeners, só muda de posição no DOM. Isso evita duplicar
  // qualquer lógica de renderização/filtro que já existe.
  const expandModal = document.getElementById('expandModal');
  const expandModalBody = document.getElementById('expandModalBody');
  const expandTarget = document.getElementById('responseExpandTarget');
  const btnExpand = document.getElementById('btnExpandResponse');
  let expandHomeParent = null;
  let expandHomeNextSibling = null;

  function openExpand() {
    expandHomeParent = expandTarget.parentNode;
    expandHomeNextSibling = expandTarget.nextSibling;
    expandModalBody.appendChild(expandTarget);
    expandModal.classList.remove('hidden');
    btnExpand.textContent = '🗗 Recolher';
    document.body.style.overflow = 'hidden';
    // A largura disponível mudou (modal é bem mais largo) — reavalia se o
    // aviso "role para o lado" ainda faz sentido nesse tamanho.
    TgaUi.updateScrollHint(TgaUi.currentView);
  }

  function closeExpand() {
    if (expandModal.classList.contains('hidden')) return;
    if (expandHomeNextSibling) {
      expandHomeParent.insertBefore(expandTarget, expandHomeNextSibling);
    } else {
      expandHomeParent.appendChild(expandTarget);
    }
    expandModal.classList.add('hidden');
    btnExpand.textContent = '⛶ Expandir';
    document.body.style.overflow = '';
    TgaUi.updateScrollHint(TgaUi.currentView);
  }

  btnExpand.addEventListener('click', () => {
    if (expandModal.classList.contains('hidden')) openExpand();
    else closeExpand();
  });
  document.getElementById('btnCloseExpand').addEventListener('click', closeExpand);
  document.querySelector('.expand-modal__backdrop').addEventListener('click', closeExpand);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeExpand();
  });

  // ---------- Gerador de código: recolhido por padrão ----------
  document.getElementById('btnToggleCodegen').addEventListener('click', (e) => {
    const wrap = document.getElementById('codegenBodyWrap');
    const nowHidden = wrap.classList.toggle('hidden'); // true = classe "hidden" acabou de ser adicionada
    e.target.textContent = nowHidden ? 'Mostrar' : 'Ocultar';
    e.target.setAttribute('aria-expanded', String(!nowHidden));
  });

  // ---------- Gerador de código: abas + token real + copiar ----------
  document.getElementById('codegenTabs').addEventListener('click', (e) => {
    const tab = e.target.closest('.view-tab');
    if (!tab) return;
    document.querySelectorAll('#codegenTabs .view-tab').forEach(t => t.classList.remove('view-tab--active'));
    tab.classList.add('view-tab--active');
    TgaUi.renderCodegen();
  });

  document.getElementById('codegenIncludeToken').addEventListener('change', () => {
    TgaUi.renderCodegen();
  });

  document.getElementById('btnCopyCodegen').addEventListener('click', async (e) => {
    const payload = e.target.dataset.payload;
    if (!payload) return;
    await navigator.clipboard.writeText(payload);
    flashButton('btnCopyCodegen', 'Copiado!');
  });

  // ---------- Modo Guiado ----------
  document.getElementById('guidedModeToggle').addEventListener('change', (e) => {
    document.getElementById('guidedPanel').classList.toggle('hidden', !e.target.checked);
  });

  function flashButton(id, tempText) {
    const btn = document.getElementById(id);
    const original = btn.textContent;
    btn.textContent = tempText;
    setTimeout(() => { btn.textContent = original; }, 1200);
  }
});
