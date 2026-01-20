<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>📚 Manuais — Administração</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">

  <style>
    /* ===== ADMIN UI ===== */
    .admin-actions {
      display: grid;
      gap: 10px;
      margin: 15px 0;
    }

    .admin-actions button {
      padding: 10px;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      background: var(--primary);
      color: #000;
      font-weight: 500;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 15px;
    }

    .form-group label {
      font-size: 13px;
      color: var(--muted);
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      padding: 10px;
      border-radius: 8px;
      border: none;
      background: var(--bg-card);
      color: var(--text);
    }

    .form-group textarea {
      resize: vertical;
      min-height: 160px;
    }

    .modal-content {
      max-width: 720px;
    }

    .modal footer {
      padding: 15px;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }

    .btn-sec {
      background: var(--bg-hover);
      color: var(--text);
    }
  </style>
</head>
<body>

<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <h2>📘 Manuais</h2>
      <span>Administração</span>
    </div>

    <div class="sidebar-search">
      <input type="text" id="buscaManual" placeholder="🔍 Buscar manual...">
    </div>

    <!-- AÇÕES ADMIN -->
    <div class="admin-actions">
      <button onclick="abrirModal('modalNovaPasta')">📁 Criar pasta</button>
      <button onclick="abrirModal('modalNovoTxt')">📝 Criar manual</button>
      <button onclick="abrirModal('modalUploadTxt')">⬆️ Importar TXT</button>
    </div>

    <nav class="menu" id="menuManuais"></nav>
  </aside>

  <!-- CONTEÚDO -->
  <main class="content">
    <div class="content-header">
      <h1>Painel Administrativo</h1>
      <p>Clique em um manual no menu para visualizar</p>
    </div>

    <div class="manual-body">
      <p>
        ✔ Visualize manuais clicando no menu<br>
        ✔ Crie, edite e importe arquivos<br>
        ✔ Estrutura segura e organizada
      </p>
    </div>
  </main>

</div>

<!-- =====================================================
     MODAL VISUALIZAÇÃO TXT (OBRIGATÓRIO PARA O app.js)
===================================================== -->
<div class="modal hidden" id="modalTxt">
  <div class="modal-content">
    <header>
      <h3 id="modalTitulo">Manual</h3>
      <div class="modal-actions">
        <button id="btnCopiarTxt">📋 Copiar</button>
        <button id="btnBaixarTxt">⬇️ Baixar</button>
        <button id="btnFechar">✖</button>
      </div>
    </header>

    <pre id="modalTexto"></pre>
  </div>
</div>

<!-- ================= MODAIS ADMIN ================= -->

<!-- MODAL NOVA PASTA -->
<div class="modal hidden" id="modalNovaPasta">
  <div class="modal-content">
    <header>
      <h3>📁 Criar nova pasta</h3>
      <button onclick="fecharModal('modalNovaPasta')">✖</button>
    </header>

    <div style="padding:20px">
      <div class="form-group">
        <label>Pasta pai</label>
        <select id="pastaPai"></select>
      </div>

      <div class="form-group">
        <label>Nome da nova pasta</label>
        <input type="text" id="novaPastaNome" placeholder="ex: produtos">
      </div>
    </div>

    <footer>
      <button class="btn-sec" onclick="fecharModal('modalNovaPasta')">Cancelar</button>
      <button onclick="criarPasta()">Criar</button>
    </footer>
  </div>
</div>

<!-- MODAL NOVO TXT -->
<div class="modal hidden" id="modalNovoTxt">
  <div class="modal-content">
    <header>
      <h3>📝 Criar novo manual</h3>
      <button onclick="fecharModal('modalNovoTxt')">✖</button>
    </header>

    <div style="padding:20px">
      <div class="form-group">
        <label>Pasta destino</label>
        <select id="txtPastaDestino"></select>
      </div>

      <div class="form-group">
        <label>Nome do arquivo (.txt)</label>
        <input type="text" id="txtNome" placeholder="como_cadastrar_produto.txt">
      </div>

      <div class="form-group">
        <label>Conteúdo</label>
        <textarea id="txtConteudo"></textarea>
      </div>
    </div>

    <footer>
      <button class="btn-sec" onclick="fecharModal('modalNovoTxt')">Cancelar</button>
      <button onclick="salvarTxt()">Salvar</button>
    </footer>
  </div>
</div>

<!-- MODAL UPLOAD -->
<div class="modal hidden" id="modalUploadTxt">
  <div class="modal-content">
    <header>
      <h3>⬆️ Importar arquivo TXT</h3>
      <button onclick="fecharModal('modalUploadTxt')">✖</button>
    </header>

    <div style="padding:20px">
      <div class="form-group">
        <label>Pasta destino</label>
        <select id="uploadPastaDestino"></select>
      </div>

      <div class="form-group">
        <label>Arquivo (.txt)</label>
        <input type="file" id="uploadArquivo" accept=".txt">
      </div>
    </div>

    <footer>
      <button class="btn-sec" onclick="fecharModal('modalUploadTxt')">Cancelar</button>
      <button onclick="importarTxt()">Importar</button>
    </footer>
  </div>
</div>

<script src="assets/js/app.js"></script>

<script>
/* ===== ADMIN HELPERS ===== */

function abrirModal(id) {
  carregarPastas();
  document.getElementById(id).classList.remove('hidden');
}

function fecharModal(id) {
  document.getElementById(id).classList.add('hidden');
}

/* ===== CARREGAR PASTAS EXISTENTES ===== */
function carregarPastas() {
  fetch('api/listar_manuais.php')
    .then(r => r.json())
    .then(data => {
      const selects = [
        'pastaPai',
        'txtPastaDestino',
        'uploadPastaDestino'
      ];

      selects.forEach(id => {
        const sel = document.getElementById(id);
        if (!sel) return;
        sel.innerHTML = '<option value="">/ (raiz)</option>';
        preencherSelect(sel, data);
      });
    });
}

function preencherSelect(select, lista, prefix = '') {
  lista.forEach(item => {
    if (item.tipo === 'pasta') {
      const path = prefix ? `${prefix}/${item.slug}` : item.slug;
      const opt = document.createElement('option');
      opt.value = path;
      opt.textContent = path;
      select.appendChild(opt);
      preencherSelect(select, item.filhos || [], path);
    }
  });
}

/* ===== AÇÕES ===== */
function criarPasta() {
  fetch('api/admin_criar_pasta.php', {
    method: 'POST',
    body: JSON.stringify({
      caminho: document.getElementById('pastaPai').value,
      nome: document.getElementById('novaPastaNome').value
    })
  }).then(() => location.reload());
}

function salvarTxt() {
  fetch('api/admin_salvar_txt.php', {
    method: 'POST',
    body: JSON.stringify({
      caminho: document.getElementById('txtPastaDestino').value,
      nome: document.getElementById('txtNome').value,
      conteudo: document.getElementById('txtConteudo').value
    })
  }).then(() => location.reload());
}

function importarTxt() {
  const form = new FormData();
  form.append('caminho', document.getElementById('uploadPastaDestino').value);
  form.append('arquivo', document.getElementById('uploadArquivo').files[0]);

  fetch('api/admin_importar_txt.php', {
    method: 'POST',
    body: form
  }).then(() => location.reload());
}
</script>

</body>
</html>
