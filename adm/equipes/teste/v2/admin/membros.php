<?php
// admin/membros.php
session_start();
require_once '../includes/auth.php';
auth_guard();
require_once '../conexao.php';

$stmt = $pdo->query("SELECT * FROM renascer_menbros ORDER BY nome_completo ASC");
$membros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$titulo_pagina = 'Membros - Igreja Renascer';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<main class="dashboard">
    <div class="topbar">
        <h1>Lista de Membros &nbsp;<span id="contadorMembros" style="font-size:.7em;font-weight:400;color:var(--text-2)"><?= count($membros) ?> cadastrados</span></h1>
        <div class="topbar-actions">
            <button class="btn btn-sm" onclick="abrirModalNovo()">+ Novo Membro</button>
        </div>
    </div>

    <div class="table-box">
        <div class="search-form">
            <input type="text" id="buscaInput" placeholder="Buscar por nome, telefone ou data..." autocomplete="off">
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:46px;"></th>
                    <th class="sortable" data-col="1" onclick="sortarTabela(1)">Nome <span class="sort-icon">↕</span></th>
                    <th class="sortable" data-col="2" onclick="sortarTabela(2)">Nascimento <span class="sort-icon">↕</span></th>
                    <th class="sortable" data-col="3" onclick="sortarTabela(3)">Telefone <span class="sort-icon">↕</span></th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaCorpo">
                <?php foreach ($membros as $m):
                    $fotoUrl = $m['foto'] ? '../assets/uploads/fotos/' . htmlspecialchars($m['foto']) : null;
                ?>
                <tr data-id="<?= $m['id'] ?>"
                    data-nome="<?= htmlspecialchars($m['nome_completo'],    ENT_QUOTES) ?>"
                    data-nascimento="<?= htmlspecialchars($m['data_nascimento'] ?? '', ENT_QUOTES) ?>"
                    data-telefone="<?= htmlspecialchars($m['telefone']      ?? '', ENT_QUOTES) ?>"
                    data-endereco="<?= htmlspecialchars($m['endereco']       ?? '', ENT_QUOTES) ?>"
                    data-conversao="<?= htmlspecialchars($m['data_conversao'] ?? '', ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($m['status'],          ENT_QUOTES) ?>"
                    data-obs="<?= htmlspecialchars($m['observacoes']         ?? '', ENT_QUOTES) ?>"
                    data-foto="<?= htmlspecialchars($m['foto'] ?? '', ENT_QUOTES) ?>"
                    data-foto-url="<?= $fotoUrl ? htmlspecialchars($fotoUrl, ENT_QUOTES) : '' ?>">
                    <td class="td-avatar">
                        <?php if ($fotoUrl): ?>
                            <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="" class="avatar-sm" loading="lazy">
                        <?php else: ?>
                            <div class="avatar-sm avatar-fallback"><?= mb_strtoupper(mb_substr($m['nome_completo'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($m['nome_completo']) ?></td>
                    <td><?= $m['data_nascimento'] ? date('d/m/Y', strtotime($m['data_nascimento'])) : '—' ?></td>
                    <td><?= htmlspecialchars($m['telefone'] ?: '—') ?></td>
                    <td>
                        <span class="badge badge-<?= $m['status'] === 'ativo' ? 'ativo' : 'inativo' ?>">
                            <?= ucfirst(htmlspecialchars($m['status'])) ?>
                        </span>
                    </td>
                    <td class="acoes">
                        <button class="btn btn-secondary btn-sm" data-action="editar">Editar</button>
                        <button class="btn btn-danger btn-sm"    data-action="excluir">Excluir</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="sem-resultado" id="semResultado" style="display:none">
                    <td colspan="6">Nenhum membro encontrado para esta busca.</td>
                </tr>
                <?php if (empty($membros)): ?>
                <tr class="sem-resultado">
                    <td colspan="6">Nenhum membro cadastrado ainda.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Paginação -->
        <div class="paginacao-wrap" id="paginacaoWrap">
            <div class="paginacao-info" id="paginacaoInfo"></div>
            <div class="paginacao-btns" id="paginacaoBtns"></div>
        </div>
    </div>
</main>

<!-- MODAL CADASTRO / EDIÇÃO -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitulo">
        <div class="modal-header">
            <h3 id="modalTitulo">Novo Membro</h3>
            <button class="modal-close" onclick="fecharModal()" aria-label="Fechar">&times;</button>
        </div>
        <div class="modal-body">
            <div id="msgModal2" class="alert" style="display:none"></div>
            <form id="formMembro" novalidate enctype="multipart/form-data">
                <input type="hidden" id="fAction" name="action" value="criar">
                <input type="hidden" id="fId"     name="id"     value="">
                <input type="hidden" id="fRemoverFoto" name="remover_foto" value="0">

                <!-- Avatar / Upload de Foto -->
                <div class="foto-upload-area">
                    <div class="foto-preview-wrap">
                        <div class="foto-preview" id="fotoPreview">
                            <span class="foto-preview-placeholder" id="fotoPlaceholder">&#128100;</span>
                            <img id="fotoImgPreview" src="" alt="" style="display:none">
                        </div>
                        <button type="button" class="foto-remove-btn" id="btnRemoverFoto" onclick="removerFoto()" style="display:none" title="Remover foto">&#10005;</button>
                    </div>
                    <div class="foto-upload-info">
                        <label for="fFoto" class="btn btn-secondary btn-sm" style="cursor:pointer;">&#128247; Escolher Foto</label>
                        <input type="file" id="fFoto" name="foto" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="previewFoto(this)">
                        <p class="foto-hint">JPG, PNG ou WEBP · máx. 3 MB</p>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="fNome">Nome Completo *</label>
                        <input type="text" id="fNome" name="nome_completo" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="fTelefone">Telefone</label>
                        <input type="text" id="fTelefone" name="telefone" class="form-control" placeholder="(65) 99999-9999">
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="fNascimento">Data de Nascimento *</label>
                        <input type="date" id="fNascimento" name="data_nascimento" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="fConversao">Data de Conversão</label>
                        <input type="date" id="fConversao" name="data_conversao" class="form-control">
                    </div>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="fEndereco">Endereço</label>
                        <input type="text" id="fEndereco" name="endereco" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="fStatus">Status</label>
                        <select id="fStatus" name="status" class="form-control">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="fObs">Observações</label>
                    <textarea id="fObs" name="observacoes" class="form-control" rows="2"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <div class="modal-footer-actions" style="width:100%;justify-content:space-between;">
                <button class="btn btn-secondary" type="button" onclick="fecharModal()">Cancelar</button>
                <button class="btn" type="button" id="btnSalvar" onclick="salvarMembro()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
/* ================================================================
   PAGINAÇÃO
   ================================================================ */
var ITENS_POR_PAGINA = 15;
var _paginaAtual = 1;
var _modosBusca = false;

function _todasLinhas() {
    return Array.from(document.querySelectorAll('#tabelaCorpo tr[data-id]'));
}

function _linhasVisiveis() {
    return _todasLinhas().filter(function (tr) { return tr.style.display !== 'none' || tr._filtrado; });
}

function paginar() {
    var todas = _todasLinhas();
    // Linhas que passaram no filtro de busca (sem display:none)
    var filtradas = todas.filter(function (tr) { return !tr._ocultaBusca; });

    var total = filtradas.length;
    var totalPags = Math.ceil(total / ITENS_POR_PAGINA) || 1;
    if (_paginaAtual > totalPags) _paginaAtual = totalPags;

    var inicio = (_paginaAtual - 1) * ITENS_POR_PAGINA;
    var fim    = inicio + ITENS_POR_PAGINA;

    filtradas.forEach(function (tr, i) {
        tr.style.display = (i >= inicio && i < fim) ? '' : 'none';
    });

    // Oculta as que foram filtradas pelo input de busca
    todas.forEach(function (tr) {
        if (tr._ocultaBusca) tr.style.display = 'none';
    });

    _renderPaginacao(total, totalPags);
    _atualizarContador();
}

function _renderPaginacao(total, totalPags) {
    var info = document.getElementById('paginacaoInfo');
    var btns = document.getElementById('paginacaoBtns');
    var wrap = document.getElementById('paginacaoWrap');

    if (total === 0) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'flex';

    var inicio = (_paginaAtual - 1) * ITENS_POR_PAGINA + 1;
    var fim    = Math.min(_paginaAtual * ITENS_POR_PAGINA, total);
    info.textContent = 'Mostrando ' + inicio + '–' + fim + ' de ' + total + ' membro' + (total !== 1 ? 's' : '');

    btns.innerHTML = '';

    // Botão Anterior
    var prev = document.createElement('button');
    prev.className = 'pag-btn' + (_paginaAtual === 1 ? ' disabled' : '');
    prev.textContent = '‹';
    prev.disabled = _paginaAtual === 1;
    prev.onclick = function () { _paginaAtual--; paginar(); };
    btns.appendChild(prev);

    // Números de página
    var delta = 2;
    var pages = [];
    for (var p = 1; p <= totalPags; p++) {
        if (p === 1 || p === totalPags || (p >= _paginaAtual - delta && p <= _paginaAtual + delta)) {
            pages.push(p);
        }
    }
    var ultimo = 0;
    pages.forEach(function (p) {
        if (ultimo && p - ultimo > 1) {
            var ell = document.createElement('span');
            ell.className = 'pag-ell';
            ell.textContent = '…';
            btns.appendChild(ell);
        }
        var btn = document.createElement('button');
        btn.className = 'pag-btn' + (p === _paginaAtual ? ' ativo' : '');
        btn.textContent = p;
        btn.onclick = (function (pg) { return function () { _paginaAtual = pg; paginar(); }; })(p);
        btns.appendChild(btn);
        ultimo = p;
    });

    // Botão Próximo
    var next = document.createElement('button');
    next.className = 'pag-btn' + (_paginaAtual === totalPags ? ' disabled' : '');
    next.textContent = '›';
    next.disabled = _paginaAtual === totalPags;
    next.onclick = function () { _paginaAtual++; paginar(); };
    btns.appendChild(next);
}

/* ================================================================
   BUSCA AO VIVO (integrada com paginação)
   ================================================================ */
document.getElementById('buscaInput').addEventListener('input', function () {
    var q = this.value.toLowerCase().trim();
    _todasLinhas().forEach(function (tr) {
        tr._ocultaBusca = q ? !tr.textContent.toLowerCase().includes(q) : false;
    });
    _paginaAtual = 1;
    paginar();
    document.getElementById('semResultado').style.display =
        (q && _todasLinhas().filter(function (tr) { return !tr._ocultaBusca; }).length === 0) ? '' : 'none';
});

/* Inicia paginação na carga da página */
paginar();

/* ================================================================
   MODAL
   ================================================================ */
var overlay = document.getElementById('modalOverlay');

function abrirModalNovo() {
    document.getElementById('formMembro').reset();
    document.getElementById('fAction').value = 'criar';
    document.getElementById('fId').value = '';
    document.getElementById('fStatus').value = 'ativo';
    document.getElementById('fRemoverFoto').value = '0';
    document.getElementById('modalTitulo').textContent = 'Novo Membro';
    _resetFotoPreview(null);
    _limparMsg();
    overlay.classList.add('ativo');
    document.getElementById('fNome').focus();
}

function abrirModalEditar(row) {
    document.getElementById('formMembro').reset();
    document.getElementById('fAction').value = 'editar';
    document.getElementById('fId').value       = row.dataset.id;
    document.getElementById('fNome').value     = row.dataset.nome;
    document.getElementById('fNascimento').value = row.dataset.nascimento;
    document.getElementById('fTelefone').value = row.dataset.telefone;
    document.getElementById('fEndereco').value  = row.dataset.endereco;
    document.getElementById('fConversao').value = row.dataset.conversao;
    document.getElementById('fStatus').value    = row.dataset.status;
    document.getElementById('fObs').value       = row.dataset.obs;
    document.getElementById('fRemoverFoto').value = '0';
    document.getElementById('modalTitulo').textContent = 'Editar Membro';
    _resetFotoPreview(row.dataset.fotoUrl || null);
    _limparMsg();
    overlay.classList.add('ativo');
    document.getElementById('fNome').focus();
}

function fecharModal() { overlay.classList.remove('ativo'); }
overlay.addEventListener('click', function (e) { if (e.target === overlay) fecharModal(); });
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fecharModal(); });

/* ================================================================
   FOTO — PREVIEW
   ================================================================ */
function previewFoto(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function (e) {
        _mostrarFotoPreview(e.target.result);
    };
    reader.readAsDataURL(input.files[0]);
    document.getElementById('fRemoverFoto').value = '0';
}

function removerFoto() {
    document.getElementById('fFoto').value = '';
    document.getElementById('fRemoverFoto').value = '1';
    _resetFotoPreview(null);
}

function _mostrarFotoPreview(src) {
    var img = document.getElementById('fotoImgPreview');
    var ph  = document.getElementById('fotoPlaceholder');
    var rem = document.getElementById('btnRemoverFoto');
    img.src = src;
    img.style.display = 'block';
    ph.style.display  = 'none';
    rem.style.display = 'flex';
}

function _resetFotoPreview(src) {
    var img = document.getElementById('fotoImgPreview');
    var ph  = document.getElementById('fotoPlaceholder');
    var rem = document.getElementById('btnRemoverFoto');
    if (src) {
        img.src = src;
        img.style.display = 'block';
        ph.style.display  = 'none';
        rem.style.display = 'flex';
    } else {
        img.src = '';
        img.style.display = 'none';
        ph.style.display  = 'block';
        rem.style.display = 'none';
    }
}

/* ================================================================
   SALVAR
   ================================================================ */
async function salvarMembro() {
    var nome = document.getElementById('fNome').value.trim();
    var nasc = document.getElementById('fNascimento').value;
    if (!nome || !nasc) { _msg('Preencha Nome e Data de Nascimento.', 'erro'); return; }

    var btn = document.getElementById('btnSalvar');
    btn.disabled = true; btn.textContent = 'Salvando…';

    var fd = new FormData(document.getElementById('formMembro'));
    try {
        var r    = await fetch('api_membro.php', { method: 'POST', body: fd });
        var data = await r.json();
        if (data.ok) {
            if (document.getElementById('fAction').value === 'criar') {
                _adicionarLinha(data.membro);
                document.getElementById('formMembro').reset();
                document.getElementById('fStatus').value = 'ativo';
                document.getElementById('fAction').value = 'criar';
                _resetFotoPreview(null);
                _msg(data.msg, 'sucesso');
            } else {
                _atualizarLinha(data.membro);
                _msg(data.msg, 'sucesso');
            }
            _paginaAtual = 1;
            paginar();
        } else {
            _msg(data.msg || 'Erro ao salvar.', 'erro');
        }
    } catch (err) {
        _msg('Erro de conexão.', 'erro');
    }
    btn.disabled = false; btn.textContent = 'Salvar';
}

/* ================================================================
   EXCLUIR
   ================================================================ */
async function excluirMembro(row) {
    var fd = new FormData();
    fd.append('action', 'excluir');
    fd.append('id', row.dataset.id);
    try {
        var r    = await fetch('api_membro.php', { method: 'POST', body: fd });
        var data = await r.json();
        if (data.ok) { row.remove(); paginar(); }
        else alert(data.msg || 'Erro ao excluir.');
    } catch (err) { alert('Erro de conexão.'); }
}

/* ================================================================
   EVENT DELEGATION
   ================================================================ */
document.getElementById('tabelaCorpo').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-action]');
    if (!btn) return;
    var row    = btn.closest('tr');
    var action = btn.dataset.action;
    if (action === 'editar') {
        abrirModalEditar(row);
    } else if (action === 'excluir') {
        if (confirm('Excluir o membro "' + row.dataset.nome + '"?')) excluirMembro(row);
    }
});

/* ================================================================
   HELPERS
   ================================================================ */
function _msg(texto, tipo) {
    var el = document.getElementById('msgModal2');
    el.textContent = texto;
    el.className   = 'alert alert-' + tipo;
    el.style.display = 'block';
}
function _limparMsg() {
    var el = document.getElementById('msgModal2');
    el.style.display = 'none';
    el.textContent = '';
}

function _adicionarLinha(m) {
    document.getElementById('semResultado').style.display = 'none';
    var tbody = document.getElementById('tabelaCorpo');
    tbody.insertBefore(_criarLinha(m), tbody.firstChild);
}
function _atualizarLinha(m) {
    var old = document.querySelector('tr[data-id="' + m.id + '"]');
    if (old) old.replaceWith(_criarLinha(m));
}

function _criarLinha(m) {
    var tr = document.createElement('tr');
    tr.dataset.id         = m.id;
    tr.dataset.nome       = m.nome_completo;
    tr.dataset.nascimento = m.data_nascimento || '';
    tr.dataset.telefone   = m.telefone || '';
    tr.dataset.endereco   = m.endereco || '';
    tr.dataset.conversao  = m.data_conversao || '';
    tr.dataset.status     = m.status;
    tr.dataset.obs        = m.observacoes || '';
    tr.dataset.foto       = m.foto || '';
    tr.dataset.fotoUrl    = m.foto_url || '';

    var scls  = m.status === 'ativo' ? 'badge-ativo' : 'badge-inativo';
    var slbl  = m.status === 'ativo' ? 'Ativo' : 'Inativo';
    var nasc  = m.data_nascimento ? _fmtData(m.data_nascimento) : '—';
    var tel   = m.telefone ? _esc(m.telefone) : '—';
    var letra = m.nome_completo ? m.nome_completo.charAt(0).toUpperCase() : '?';

    var avatarHtml = m.foto_url
        ? '<img src="' + _esc(m.foto_url) + '" alt="" class="avatar-sm" loading="lazy">'
        : '<div class="avatar-sm avatar-fallback">' + _esc(letra) + '</div>';

    tr.innerHTML =
        '<td class="td-avatar">' + avatarHtml + '</td>' +
        '<td>' + _esc(m.nome_completo) + '</td>' +
        '<td>' + nasc + '</td>' +
        '<td>' + tel  + '</td>' +
        '<td><span class="badge ' + scls + '">' + slbl + '</span></td>' +
        '<td class="acoes">' +
            '<button class="btn btn-secondary btn-sm" data-action="editar">Editar</button> ' +
            '<button class="btn btn-danger btn-sm" data-action="excluir">Excluir</button>' +
        '</td>';
    return tr;
}

function _fmtData(d) {
    if (!d) return '—';
    var p = d.split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
}
function _esc(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}
function _atualizarContador() {
    var total = _todasLinhas().filter(function (tr) { return !tr._ocultaBusca; }).length;
    var el = document.getElementById('contadorMembros');
    if (el) {
        var buscando = document.getElementById('buscaInput').value.trim();
        var todos = _todasLinhas().length;
        el.textContent = buscando ? (total + ' encontrado' + (total !== 1 ? 's' : '') + ' de ' + todos) : (todos + ' cadastrado' + (todos !== 1 ? 's' : ''));
    }
}

/* ================================================================
   ORDENAÇÃO
   ================================================================ */
var _sortCol = -1;
var _sortAsc = true;

function sortarTabela(col) {
    var tbody = document.getElementById('tabelaCorpo');
    var rows  = Array.from(tbody.querySelectorAll('tr[data-id]'));

    if (_sortCol === col) {
        _sortAsc = !_sortAsc;
    } else {
        _sortCol = col;
        _sortAsc = true;
    }

    rows.sort(function (a, b) {
        var va = a.cells[col] ? a.cells[col].textContent.trim() : '';
        var vb = b.cells[col] ? b.cells[col].textContent.trim() : '';

        if (col === 2) {
            va = va === '—' ? '' : va.split('/').reverse().join('-');
            vb = vb === '—' ? '' : vb.split('/').reverse().join('-');
        }

        if (va === '' && vb !== '') return 1;
        if (vb === '' && va !== '') return -1;

        var cmp = va.localeCompare(vb, 'pt-BR', { sensitivity: 'base', numeric: true });
        return _sortAsc ? cmp : -cmp;
    });

    rows.forEach(function (r) { tbody.appendChild(r); });

    document.querySelectorAll('th.sortable').forEach(function (th) {
        var icon = th.querySelector('.sort-icon');
        if (!icon) return;
        var c = parseInt(th.dataset.col, 10);
        if (c === _sortCol) {
            icon.textContent = _sortAsc ? '↑' : '↓';
            th.classList.add('sort-ativo');
        } else {
            icon.textContent = '↕';
            th.classList.remove('sort-ativo');
        }
    });

    _paginaAtual = 1;
    paginar();
}
</script>

<?php require_once '../includes/footer.php'; ?>
