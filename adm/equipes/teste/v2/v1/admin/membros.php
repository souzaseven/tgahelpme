<?php
// admin/membros.php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'maiara') {
    header('Location: login.php');
    exit;
}
require_once '../conexao.php';

// Carrega todos os membros (busca ao vivo é feita no client-side)
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
                    <th class="sortable" data-col="0" onclick="sortarTabela(0)">Nome <span class="sort-icon">↕</span></th>
                    <th class="sortable" data-col="1" onclick="sortarTabela(1)">Nascimento <span class="sort-icon">↕</span></th>
                    <th class="sortable" data-col="2" onclick="sortarTabela(2)">Telefone <span class="sort-icon">↕</span></th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="tabelaCorpo">
                <?php foreach ($membros as $m): ?>
                <tr data-id="<?= $m['id'] ?>"
                    data-nome="<?= htmlspecialchars($m['nome_completo'],    ENT_QUOTES) ?>"
                    data-nascimento="<?= htmlspecialchars($m['data_nascimento'] ?? '', ENT_QUOTES) ?>"
                    data-telefone="<?= htmlspecialchars($m['telefone']      ?? '', ENT_QUOTES) ?>"
                    data-endereco="<?= htmlspecialchars($m['endereco']       ?? '', ENT_QUOTES) ?>"
                    data-conversao="<?= htmlspecialchars($m['data_conversao'] ?? '', ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($m['status'],          ENT_QUOTES) ?>"
                    data-obs="<?= htmlspecialchars($m['observacoes']         ?? '', ENT_QUOTES) ?>">
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
                    <td colspan="5">Nenhum membro encontrado para esta busca.</td>
                </tr>
                <?php if (empty($membros)): ?>
                <tr class="sem-resultado">
                    <td colspan="5">Nenhum membro cadastrado ainda.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
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
            <div id="msgModal" class="alert" style="display:none"></div>
            <form id="formMembro" novalidate>
                <input type="hidden" id="fAction" name="action" value="criar">
                <input type="hidden" id="fId"     name="id"     value="">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="fNome">Nome Completo *</label>
                        <input type="text" id="fNome" name="nome_completo" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="fTelefone">Telefone</label>
                        <input type="text" id="fTelefone" name="telefone" class="form-control" placeholder="(11) 99999-9999">
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
            <div id="msgModal2" class="alert" style="display:none;flex:1;margin:0;padding:8px 12px;font-size:.85em;"></div>
            <div class="modal-footer-actions">
                <button class="btn btn-secondary" type="button" onclick="fecharModal()">Cancelar</button>
                <button class="btn" type="button" id="btnSalvar" onclick="salvarMembro()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
/* ===== BUSCA AO VIVO ===== */
document.getElementById('buscaInput').addEventListener('input', function () {
    var q = this.value.toLowerCase().trim();
    var visiveis = 0;
    document.querySelectorAll('#tabelaCorpo tr[data-id]').forEach(function (tr) {
        var match = !q || tr.textContent.toLowerCase().includes(q);
        tr.style.display = match ? '' : 'none';
        if (match) visiveis++;
    });
    document.getElementById('semResultado').style.display = (q && visiveis === 0) ? '' : 'none';
});

/* ===== MODAL ===== */
var overlay = document.getElementById('modalOverlay');

function abrirModalNovo() {
    document.getElementById('formMembro').reset();
    document.getElementById('fAction').value = 'criar';
    document.getElementById('fId').value = '';
    document.getElementById('fStatus').value = 'ativo';
    document.getElementById('modalTitulo').textContent = 'Novo Membro';
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
    document.getElementById('modalTitulo').textContent = 'Editar Membro';
    _limparMsg();
    overlay.classList.add('ativo');
    document.getElementById('fNome').focus();
}

function fecharModal() {
    overlay.classList.remove('ativo');
}

/* Fecha ao clicar fora */
overlay.addEventListener('click', function (e) { if (e.target === overlay) fecharModal(); });
/* Fecha com Escape */
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fecharModal(); });

/* ===== SAVE ===== */
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
                _msg(data.msg, 'sucesso');
            } else {
                _atualizarLinha(data.membro);
                _msg(data.msg, 'sucesso');
            }
            _atualizarContador();
        } else {
            _msg(data.msg || 'Erro ao salvar.', 'erro');
        }
    } catch (err) {
        _msg('Erro de conexão.', 'erro');
    }
    btn.disabled = false; btn.textContent = 'Salvar';
}

/* ===== EXCLUIR ===== */
async function excluirMembro(row) {
    var fd = new FormData();
    fd.append('action', 'excluir');
    fd.append('id', row.dataset.id);
    try {
        var r    = await fetch('api_membro.php', { method: 'POST', body: fd });
        var data = await r.json();
        if (data.ok) { row.remove(); _atualizarContador(); }
        else alert(data.msg || 'Erro ao excluir.');
    } catch (err) { alert('Erro de conexão.'); }
}

/* ===== EVENT DELEGATION (tabela) ===== */
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

/* ===== HELPERS ===== */
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

    var scls  = m.status === 'ativo' ? 'badge-ativo' : 'badge-inativo';
    var slbl  = m.status === 'ativo' ? 'Ativo' : 'Inativo';
    var nasc  = m.data_nascimento ? _fmtData(m.data_nascimento) : '—';
    var tel   = m.telefone ? _esc(m.telefone) : '—';

    tr.innerHTML =
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
    var n  = document.querySelectorAll('#tabelaCorpo tr[data-id]').length;
    var el = document.getElementById('contadorMembros');
    if (el) el.textContent = n + ' cadastrados';
}

/* ===== ORDENAÇÃO ===== */
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

        /* Nascimento: converte dd/mm/yyyy → yyyy-mm-dd para comparação correta */
        if (col === 1) {
            va = va === '—' ? '' : va.split('/').reverse().join('-');
            vb = vb === '—' ? '' : vb.split('/').reverse().join('-');
        }

        if (va === '' && vb !== '') return 1;
        if (vb === '' && va !== '') return -1;

        var cmp = va.localeCompare(vb, 'pt-BR', { sensitivity: 'base', numeric: true });
        return _sortAsc ? cmp : -cmp;
    });

    rows.forEach(function (r) { tbody.appendChild(r); });

    /* Atualiza ícones */
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
}
</script>

<?php require_once '../includes/footer.php'; ?>
