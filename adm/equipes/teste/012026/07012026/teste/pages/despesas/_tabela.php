<!-- ── Tabela de lançamentos ──────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Lançamentos</div>
            <div class="card-subtitle" id="tabelaInfo">—</div>
        </div>
        <button class="btn btn-ghost btn-sm no-print" onclick="window.print()">
            <i class="fa-solid fa-print fa-xs"></i> Imprimir
        </button>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:36px;padding-right:0"><input type="checkbox" id="cbTodos" style="accent-color:var(--indigo);width:15px;height:15px;cursor:pointer" onclick="toggleTodos(this)" title="Selecionar todos"></th>
                    <th class="sortable" onclick="sortarTabela('descricao')">Descrição <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th class="sortable" onclick="sortarTabela('cat_nome')">Categoria <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th>Conta / Cartão</th>
                    <th>Responsável</th>
                    <th class="sortable" onclick="sortarTabela('data')">Data <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th class="sortable" onclick="sortarTabela('status')">Status <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th class="sortable text-right" onclick="sortarTabela('valor')">Valor <i class="fa-solid fa-arrows-up-down fa-xs sort-ic"></i></th>
                    <th style="width:76px"></th>
                </tr>
            </thead>
            <tbody id="tabelaBody">
                <tr>
                    <td colspan="9" class="empty-state">
                        <i class="fa-solid fa-spinner fa-spin"></i> Carregando...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="cards-mobile" id="cardsMobile"></div>
    <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
        <span class="text-sm text-muted" id="pagInfo">—</span>
        <div class="d-flex align-center gap-1">
            <select id="porPagina" class="form-control" style="width:auto;font-size:.8rem"
                    onchange="_paginaDes=1;carregarDados()">
                <option value="25">25 / pág</option>
                <option value="50">50 / pág</option>
                <option value="100">100 / pág</option>
            </select>
            <div id="pagBtns" class="d-flex gap-1"></div>
        </div>
    </div>
</div>
