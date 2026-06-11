/* =========================================================
   API Helper — Fetch centralizado
   
   Responsabilidades:
    - Injeta o token CSRF (window.__CSRF__) em toda requisição
    - Detecta sessão/CSRF expirado (HTTP 401/403 ou mensagem do backend)
      e redireciona para login.php automaticamente
    - Exibe toast de erro para falhas lógicas da API
    - Evita toasts duplicados quando já está redirecionando
========================================================= */

async function apiFetch(url, options = {}) {
  const config = {
    method: options.method || 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.__CSRF__ || ''
    },
    body: options.body ? JSON.stringify(options.body) : null
  };

  try {
    const res = await fetch(url, config);

    /* 🚨 Sessão ou CSRF expirado (HTTP) */
    if (res.status === 401 || res.status === 403) {
      redirectToLogin();
      throw new Error('Sessão expirada');
    }

    let data;
    try {
      data = await res.json();
    } catch (e) {
      redirectToLogin();
      throw new Error('Resposta inválida do servidor');
    }

    /* 🚨 CSRF inválido retornado pelo backend */
    if (
      data?.message &&
      typeof data.message === 'string' &&
      data.message.toLowerCase().includes('csrf')
    ) {
      redirectToLogin();
      throw new Error('Sessão expirada');
    }

    /* 🚨 Erro lógico da API */
    if (!res.ok || data.error || data.success === false) {
      throw new Error(data.message || 'Erro na requisição');
    }

    return data;

  } catch (err) {
    // evita toast repetido quando já está redirecionando
    if (!window.__REDIRECTING__) {
      showToast(err.message, 'danger');
    }
    throw err;
  }
}

/* =========================================================
   Redirect centralizado para login
========================================================= */
function redirectToLogin() {
  if (window.__REDIRECTING__) return;
  window.__REDIRECTING__ = true;

  // Pequena pausa para o toast de "Sessão expirada" ser lido antes do redirect.
  // Uso caminho relativo para que funcione em qualquer ambiente (dev, prod, subpasta).
  setTimeout(() => {
    window.location.href = 'login.php';
  }, 300);
}

/* =========================================================
   UTILITÁRIOS GLOBAIS
========================================================= */
function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(String(text))
    .then(() => showToast(`Copiado: ${text}`, 'success'))
    .catch(() => showToast('Falha ao copiar', 'danger'));
}

/* Delegação global para botões .btn-copy (valor estático — tabelas) */
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn-copy');
  if (!btn) return;
  copyToClipboard(btn.dataset.copy);
});

/* Delegação global para botões .btn-copy-field (valor atual do campo — modais) */
document.addEventListener('click', e => {
  const btn = e.target.closest('.btn-copy-field');
  if (!btn) return;
  e.preventDefault();
  const name  = btn.dataset.copyTarget;
  const field = name
    ? document.querySelector(`[name="${name}"]`)
    : btn.closest('.form-group')?.querySelector('input, textarea, select');
  const val = field?.value?.trim();
  if (val) copyToClipboard(val);
});

/* =========================================================
   MULTI-SELECT DROPDOWN
   Uso:
     const ms = new MultiSelect('elementId', {
       label:    'Status',
       options:  [{ value: 'ATIVO', label: 'ATIVO' }],
       onChange: (values) => { ... },
       selected: ['ATIVO']   // opcional
     });
     ms.getValues()         → string[]
     ms.setValues(['A'])    → atualiza checkboxes
     ms.setOptions([...])   → troca as opções (preserva seleção)
========================================================= */
class MultiSelect {
  constructor(wrapperId, opts = {}) {
    this.el        = document.getElementById(wrapperId);
    if (!this.el) return;
    this.baseLabel = opts.label   || '';
    this.onChange  = opts.onChange || (() => {});
    this._build(opts.options || []);
    if (opts.selected && opts.selected.length) this.setValues(opts.selected);
    this._updateLabel();
    this._bindClose();
  }

  _build(options) {
    this.el.className   = 'ms-wrapper';
    this.el.innerHTML   = `
      <button class="ms-toggle" type="button">
        <span class="ms-lbl"></span>
        <i class="fas fa-chevron-down ms-caret"></i>
      </button>
      <div class="ms-panel">
        <div class="ms-panel-header">
          <span style="font-size:.7rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em">Filtrar</span>
          <button type="button" class="ms-clear-btn">Limpar</button>
        </div>
      </div>`;
    this.toggle   = this.el.querySelector('.ms-toggle');
    this.panel    = this.el.querySelector('.ms-panel');
    this.lbl      = this.el.querySelector('.ms-lbl');
    this.clearBtn = this.el.querySelector('.ms-clear-btn');

    this._addOptions(options);

    this.toggle.addEventListener('click', e => {
      e.stopPropagation();
      this._open(!this.panel.classList.contains('ms-open'));
    });

    this.clearBtn.addEventListener('click', e => {
      e.stopPropagation();
      this.setValues([]);
      this.onChange([]);
    });
  }

  _addOptions(options) {
    if (!options.length) {
      const empty = document.createElement('div');
      empty.className   = 'ms-empty';
      empty.textContent = 'Carregando…';
      this.panel.appendChild(empty);
      return;
    }
    options.forEach(o => {
      const lbl = document.createElement('label');
      lbl.className = 'ms-opt';
      lbl.innerHTML = `<input type="checkbox" value="${this._esc(o.value)}"> ${this._esc(o.label)}`;
      lbl.querySelector('input').addEventListener('change', () => {
        this._updateLabel();
        this.onChange(this.getValues());
      });
      this.panel.appendChild(lbl);
    });
  }

  _open(state) {
    this.panel.classList.toggle('ms-open', state);
    this.toggle.classList.toggle('ms-active', state);

    /* fechar outros dropdowns abertos */
    if (state) {
      document.querySelectorAll('.ms-panel.ms-open').forEach(p => {
        if (p !== this.panel) {
          p.classList.remove('ms-open');
          p.closest('.ms-wrapper')?.querySelector('.ms-toggle')?.classList.remove('ms-active');
        }
      });
    }
  }

  _bindClose() {
    document.addEventListener('click', e => {
      if (!this.el.contains(e.target)) this._open(false);
    });
  }

  _updateLabel() {
    const vals = this.getValues();
    if (!vals.length) {
      this.lbl.textContent = `${this.baseLabel}: Todos`;
      this.toggle.classList.remove('ms-has-filter');
    } else if (vals.length === 1) {
      const text = this.panel.querySelector(`input[value="${CSS.escape(vals[0])}"]`)
        ?.closest('.ms-opt')?.textContent.trim() || vals[0];
      this.lbl.textContent = `${this.baseLabel}: ${text}`;
      this.toggle.classList.add('ms-has-filter');
    } else {
      this.lbl.textContent = `${this.baseLabel}: ${vals.length} selecionados`;
      this.toggle.classList.add('ms-has-filter');
    }
  }

  getValues() {
    return [...this.panel.querySelectorAll('input[type="checkbox"]:checked')]
      .map(c => c.value);
  }

  setValues(values) {
    this.panel.querySelectorAll('input[type="checkbox"]').forEach(c => {
      c.checked = values.includes(c.value);
    });
    this._updateLabel();
  }

  setOptions(options, preserveSelected = true) {
    const current = preserveSelected ? this.getValues() : [];
    /* remove todas as opções (mantém header) */
    [...this.panel.querySelectorAll('.ms-opt, .ms-empty')].forEach(n => n.remove());
    this._addOptions(options);
    if (current.length) this.setValues(current);
    this._updateLabel();
  }

  _esc(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }
}