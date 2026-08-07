<?php
$p = TABLE_PREFIX;

$contasImp = $pdo->query(
    "SELECT id, nome FROM `{$p}contas` WHERE ativo=1 ORDER BY nome"
)->fetchAll();

$catsImp = $pdo->query(
    "SELECT id, nome, tipo, cor FROM `{$p}categorias` WHERE ativo=1 ORDER BY tipo, nome"
)->fetchAll();
?>

<style>
.imp-drop { border:2px dashed var(--border); border-radius:var(--radius-lg); padding:3rem; text-align:center;
            transition:var(--ease); cursor:pointer; background:var(--bg-800); }
.imp-drop:hover, .imp-drop.over { border-color:var(--indigo); background:rgba(99,102,241,.04); }
.imp-drop input[type=file] { display:none }
.imp-table td, .imp-table th { font-size:.8rem; padding:.5rem .75rem }
.imp-check { width:16px;height:16px;cursor:pointer; accent-color:var(--indigo) }
</style>

<div class="page-header">
    <div>
        <div class="page-title">Importar Extrato</div>
        <div class="page-sub">Importe arquivos OFX (todos os bancos) ou CSV</div>
    </div>
</div>

<!-- Passo 1: Upload -->
<div id="passo1">
    <div class="card" style="max-width:600px;margin:0 auto">
        <div class="card-header">
            <div class="card-title">Selecione o arquivo</div>
            <div class="card-subtitle">Formatos suportados: OFX, OFC, CSV</div>
        </div>
        <div class="card-body">
            <div class="imp-drop" id="dropZone" onclick="document.getElementById('fileInput').click()"
                 ondragover="event.preventDefault();this.classList.add('over')"
                 ondragleave="this.classList.remove('over')"
                 ondrop="event.preventDefault();this.classList.remove('over');processarArquivo(event.dataTransfer.files[0])">
                <input type="file" id="fileInput" accept=".ofx,.ofc,.csv,.txt"
                       onchange="processarArquivo(this.files[0])">
                <i class="fa-solid fa-file-arrow-up fa-2x" style="color:var(--indigo);margin-bottom:.75rem;display:block"></i>
                <div class="fw-600">Arraste o arquivo aqui ou clique para selecionar</div>
                <div class="text-xs text-muted" style="margin-top:.35rem">OFX · OFC · CSV</div>
            </div>
            <div class="form-group" style="margin-top:1.25rem">
                <label class="form-label">Conta destino <span style="color:var(--rose)">*</span></label>
                <select id="impConta" class="form-control">
                    <option value="">— Selecione a conta —</option>
                    <?php foreach ($contasImp as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Passo 2: Preview e confirmação -->
<div id="passo2" style="display:none">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
        <div>
            <div class="fw-700" id="impResumo">—</div>
            <div class="text-xs text-muted" id="impArquivo"></div>
        </div>
        <div style="display:flex;gap:.75rem">
            <button class="btn btn-ghost btn-sm" onclick="resetarImportacao()">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </button>
            <button class="btn btn-primary btn-sm" onclick="confirmarImportacao()" id="btnImportar">
                <i class="fa-solid fa-file-import"></i> Importar Selecionados
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:.75rem">
                <input type="checkbox" class="imp-check" id="checkAll"
                       onchange="document.querySelectorAll('.imp-linha-cb').forEach(cb=>cb.checked=this.checked)">
                <label for="checkAll" class="text-sm fw-600">Selecionar todos</label>
            </div>
            <div class="text-xs text-muted" id="impContadorSel">0 selecionados</div>
        </div>
        <div class="table-wrap">
            <table class="imp-table">
                <thead>
                    <tr>
                        <th style="width:32px"></th>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Tipo</th>
                        <th>Categoria</th>
                        <th class="text-right">Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="impTabelaBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
let _impTransacoes = [];

const _catsImp = <?= json_encode($catsImp) ?>;

// esc()/brl() vêm de assets/js/app.js (carregado globalmente por index.php)

let _impProcessando = false;

async function processarArquivo(file) {
    if (!file || _impProcessando) return;
    if (!document.getElementById('impConta').value) {
        toast('Selecione a conta destino antes de importar.', 'warning');
        return;
    }
    document.getElementById('impArquivo').textContent = file.name + ' · ' + (file.size/1024).toFixed(1) + ' KB';

    const fd = new FormData();
    fd.append('arquivo', file);

    _impProcessando = true;
    const dropZone = document.getElementById('dropZone');
    dropZone.style.pointerEvents = 'none';
    dropZone.style.opacity = '.6';

    try {
        const res  = await fetch('backend/api/importar.php?acao=parsear', { method:'POST', body:fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);
        _impTransacoes = json.transacoes;
        renderPreview(json.transacoes);
    } catch(e) { toast('Erro ao processar arquivo: ' + e.message, 'error'); }
    finally {
        _impProcessando = false;
        dropZone.style.pointerEvents = '';
        dropZone.style.opacity = '';
    }
}

function renderPreview(lista) {
    const tbody = document.getElementById('impTabelaBody');
    if (!lista.length) { toast('Nenhuma transação encontrada no arquivo.', 'warning'); return; }

    const catOptions = _catsImp.map(c =>
        `<option value="${c.id}">${esc(c.nome)}</option>`
    ).join('');

    tbody.innerHTML = lista.map((tx, i) => `
        <tr>
            <td><input type="checkbox" class="imp-check imp-linha-cb" checked
                       onchange="atualizarContador()"
                       data-idx="${i}"></td>
            <td class="text-sm">${esc(tx.data_fmt)}</td>
            <td class="text-sm fw-600" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                title="${esc(tx.descricao)}">${esc(tx.descricao)}</td>
            <td>
                <span class="badge ${tx.tipo === 'receita' ? 'pago' : 'pendente'}"
                      style="background:${tx.tipo==='receita'?'rgba(16,185,129,.15)':'rgba(239,68,68,.15)'};
                             color:${tx.tipo==='receita'?'var(--emerald)':'var(--rose)'}">
                    ${tx.tipo === 'receita' ? '↑ Receita' : '↓ Despesa'}
                </span>
            </td>
            <td>
                <select class="form-control" style="font-size:.75rem;padding:.25rem .5rem;min-width:130px"
                        data-cat-idx="${i}">
                    <option value="">— Sem categoria —</option>
                    ${catOptions}
                </select>
            </td>
            <td class="text-right fw-600 ${tx.tipo==='receita'?'text-emerald':'text-rose'}">
                ${brl(tx.valor)}
            </td>
            <td>
                <select class="form-control" style="font-size:.75rem;padding:.25rem .5rem" data-status-idx="${i}">
                    <option value="pago">Pago</option>
                    <option value="pendente">Pendente</option>
                </select>
            </td>
        </tr>`
    ).join('');

    document.getElementById('impResumo').textContent =
        lista.length + ' transações encontradas';
    atualizarContador();
    document.getElementById('passo1').style.display = 'none';
    document.getElementById('passo2').style.display = '';
}

function atualizarContador() {
    const sel = document.querySelectorAll('.imp-linha-cb:checked').length;
    document.getElementById('impContadorSel').textContent = sel + ' selecionadas';
    document.getElementById('btnImportar').disabled = sel === 0;
}

async function confirmarImportacao() {
    const contaId = document.getElementById('impConta').value;
    if (!contaId) { toast('Conta não selecionada.', 'warning'); return; }

    const checkboxes = document.querySelectorAll('.imp-linha-cb:checked');
    const payload = [];

    checkboxes.forEach(cb => {
        const i  = +cb.dataset.idx;
        const tx = _impTransacoes[i];
        const catSel    = document.querySelector(`[data-cat-idx="${i}"]`)?.value || null;
        const statusSel = document.querySelector(`[data-status-idx="${i}"]`)?.value || 'pago';
        payload.push({ ...tx, conta_id: contaId, categoria_id: catSel, status: statusSel });
    });

    const btn = document.getElementById('btnImportar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importando...';

    try {
        const res  = await fetch('backend/api/importar.php?acao=salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transacoes: payload }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.erro);

        toast(`${json.importadas} transações importadas com sucesso!`, 'success');
        resetarImportacao();
    } catch(e) {
        toast('Erro ao importar: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-file-import"></i> Importar Selecionados';
    }
}

function resetarImportacao() {
    _impTransacoes = [];
    document.getElementById('fileInput').value = '';
    document.getElementById('passo1').style.display = '';
    document.getElementById('passo2').style.display = 'none';
    document.getElementById('impTabelaBody').innerHTML = '';
}
</script>
