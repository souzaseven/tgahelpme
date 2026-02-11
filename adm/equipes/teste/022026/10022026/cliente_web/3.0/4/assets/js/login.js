/**
 * login.js — TGA Clientes Web
 *
 * Atualização principal em relação à versão anterior:
 *  - Leio o token CSRF do <meta name="csrf-token"> gerado pelo login.php
 *    e o envio no header X-CSRF-Token de cada requisição fetch.
 *  - O backend/login.php valida esse header antes de processar o login.
 *
 * O restante (validação inline, show/hide senha, loading state,
 * proteção brute-force client-side) permanece idêntico.
 */

const form       = document.getElementById('loginForm');
const btnLogin   = document.getElementById('btnLogin');
const msgGlobal  = document.getElementById('loginMsg');
const toggleBtn  = document.getElementById('toggleSenha');
const senhaInput = document.getElementById('senha');

// ── Token CSRF ─────────────────────────────────────────────────────────────
// Leio do <meta> injetado pelo login.php. Se não existir (ex: carregamento
// direto do .html sem PHP), deixo vazio — o backend vai rejeitar por segurança.
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ── Show / Hide senha ──────────────────────────────────────────────────────
if (toggleBtn && senhaInput) {
  toggleBtn.addEventListener('click', () => {
    const isHidden  = senhaInput.type === 'password';
    senhaInput.type = isHidden ? 'text' : 'password';

    const eyeClosed = toggleBtn.querySelector('.icon-eye');
    const eyeOpen   = toggleBtn.querySelector('.icon-eye-open');
    eyeClosed.style.display = isHidden ? 'none' : '';
    eyeOpen.style.display   = isHidden ? ''     : 'none';

    toggleBtn.setAttribute('aria-label', isHidden ? 'Ocultar senha' : 'Mostrar senha');
  });
}

// ── Limpa erro do campo ao digitar ────────────────────────────────────────
['usuario', 'senha'].forEach(id => {
  const el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('input', () => {
    clearFieldError(
      id === 'usuario' ? 'group-usuario' : 'group-senha',
      id === 'usuario' ? 'err-usuario'   : 'err-senha'
    );
  });
});

// ── Proteção brute-force client-side ──────────────────────────────────────
// Bloqueia por 60 segundos após 5 tentativas erradas.
// Complementa (não substitui) a proteção server-side.
const BLOCK_AFTER = 5;
const BLOCK_TIME  = 60; // segundos
const STORAGE_KEY = 'tga_login_attempts';

function getAttempts() {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : { count: 0, since: null };
  } catch { return { count: 0, since: null }; }
}

function saveAttempts(data) {
  try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data)); } catch {}
}

function isBlocked() {
  const a = getAttempts();
  if (a.count < BLOCK_AFTER) return false;
  const elapsed = (Date.now() - a.since) / 1000;
  if (elapsed >= BLOCK_TIME) {
    saveAttempts({ count: 0, since: null });
    return false;
  }
  return Math.ceil(BLOCK_TIME - elapsed); // segundos restantes
}

function registerFailure() {
  const a     = getAttempts();
  const count = a.count + 1;
  saveAttempts({ count, since: a.since || Date.now() });
}

function clearAttempts() {
  saveAttempts({ count: 0, since: null });
}

// ── Helpers de UI ─────────────────────────────────────────────────────────
function setLoading(on) {
  btnLogin.disabled = on;
  btnLogin.classList.toggle('loading', on);
}

function showGlobalMsg(text, type = 'error') {
  msgGlobal.textContent = text;
  msgGlobal.className   = `login-msg ${type}`;
}

function clearGlobalMsg() {
  msgGlobal.textContent = '';
  msgGlobal.className   = 'login-msg';
}

function setFieldError(groupId, errId, msg) {
  const group = document.getElementById(groupId);
  const err   = document.getElementById(errId);
  if (group) group.classList.add('has-error');
  if (err)   err.textContent = msg;
}

function clearFieldError(groupId, errId) {
  const group = document.getElementById(groupId);
  const err   = document.getElementById(errId);
  if (group) group.classList.remove('has-error');
  if (err)   err.textContent = '';
  clearGlobalMsg();
}

// ── Validação client-side ─────────────────────────────────────────────────
function validate(usuario, senha) {
  let ok = true;

  if (!usuario) {
    setFieldError('group-usuario', 'err-usuario', 'Informe seu usuário');
    ok = false;
  }

  if (!senha) {
    setFieldError('group-senha', 'err-senha', 'Informe sua senha');
    ok = false;
  } else if (senha.length < 3) {
    setFieldError('group-senha', 'err-senha', 'Senha muito curta');
    ok = false;
  }

  return ok;
}

// ── Submit ────────────────────────────────────────────────────────────────
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  clearGlobalMsg();

  const usuario = document.getElementById('usuario')?.value.trim();
  const senha   = senhaInput?.value;

  if (!validate(usuario, senha)) return;

  const blocked = isBlocked();
  if (blocked) {
    showGlobalMsg(`Muitas tentativas. Aguarde ${blocked}s para tentar novamente.`);
    return;
  }

  setLoading(true);

  try {
    const res = await fetch('backend/login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        // Envio o token CSRF gerado pelo login.php no header.
        // O backend/login.php valida esse header antes de qualquer coisa.
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({ nome: usuario, senha })
    });

    // Resposta não-JSON indica erro inesperado de servidor
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      throw new Error('Resposta inesperada do servidor');
    }

    const json = await res.json();

    if (!json.success) {
      registerFailure();
      const attempts  = getAttempts();
      const remaining = BLOCK_AFTER - attempts.count;

      let msg = json.message || 'Usuário ou senha inválidos';
      if (remaining <= 2 && remaining > 0) {
        msg += ` (${remaining} tentativa${remaining > 1 ? 's' : ''} restante)`;
      }

      showGlobalMsg(msg);
      senhaInput?.select();
      return;
    }

    // ✅ Login OK
    clearAttempts();
    showGlobalMsg('Acesso autorizado! Redirecionando…', 'success');
    btnLogin.classList.remove('loading');
    btnLogin.innerHTML = '<span class="btn-text">✓ Entrando</span>';

    setTimeout(() => {
      window.location.href = 'index.php';
    }, 600);

  } catch (err) {
    console.error('[login]', err);
    showGlobalMsg('Erro de conexão com o servidor. Tente novamente.');
  } finally {
    if (msgGlobal.classList.contains('error') || msgGlobal.textContent.includes('Erro')) {
      setLoading(false);
    }
  }
});