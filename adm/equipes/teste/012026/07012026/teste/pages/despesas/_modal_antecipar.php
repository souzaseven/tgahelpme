<?php
// ============================================================
// _modal_antecipar.php — Modal de antecipação/pagamento de
// parcela de empréstimo. Partial de pages/despesas.php — usa
// $contasDes do arquivo pai.
// ============================================================
?>
<!-- ── Modal: Antecipar / Pagar Parcela de Empréstimo ─── -->
<div id="modalAntecipar" class="modal-overlay" onclick="if(event.target===this)fecharModalAntecipar()">
    <div class="modal-box" style="max-width:480px">
        <div class="modal-header">
            <div class="modal-title" id="antTitulo">Pagamento de Parcela</div>
            <button type="button" class="btn-icon" onclick="fecharModalAntecipar()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">

            <!-- Cabeçalho do empréstimo -->
            <div style="background:var(--bg-700);border-radius:var(--radius);padding:.875rem 1rem;margin-bottom:1.25rem">
                <div class="fw-700 text-sm" id="antEmpNome">—</div>
                <div class="text-xs text-muted" id="antParcelaInfo" style="margin-top:.25rem">—</div>
            </div>

            <!-- Valores de referência -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1.25rem">
                <div style="background:var(--bg-700);border-radius:var(--radius);padding:.75rem">
                    <div class="text-xs text-muted">Valor da parcela</div>
                    <div class="fw-700" id="antValorOriginalRef" style="margin-top:.2rem">—</div>
                </div>
                <div style="background:var(--bg-700);border-radius:var(--radius);padding:.75rem">
                    <div class="text-xs text-muted">Saldo devedor</div>
                    <div class="fw-700 text-rose" id="antSaldoRef" style="margin-top:.2rem">—</div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:1.1rem">
                <label class="form-label">Data do pagamento</label>
                <input type="date" id="antData" class="form-control">
            </div>

            <div class="form-group" style="margin-bottom:1.25rem">
                <label class="form-label">
                    Valor pago (R$)
                    <span style="font-size:.73rem;color:var(--text-500);font-weight:400">
                        — informe o valor negociado com o banco
                    </span>
                </label>
                <input type="text" id="antValorPago" class="form-control" data-currency inputmode="numeric">
            </div>

            <!-- Comparativo em tempo real -->
            <div id="antComparativo" style="background:var(--bg-700);border-radius:var(--radius);padding:.875rem 1rem;display:none">
                <div class="text-xs fw-600 text-muted" style="margin-bottom:.6rem;text-transform:uppercase;letter-spacing:.05em">
                    Comparativo
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.2rem 0;font-size:.85rem">
                    <span class="text-muted">Pagaria (na data certa)</span>
                    <span id="antCmpOriginal" class="fw-600">—</span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.2rem 0;font-size:.85rem">
                    <span class="text-muted">Vai pagar agora</span>
                    <span id="antCmpPago" class="fw-600">—</span>
                </div>
                <!-- Economia -->
                <div id="antEconWrap" style="border-top:1px solid var(--border);margin-top:.5rem;padding-top:.6rem;display:none">
                    <div style="display:flex;justify-content:space-between;font-size:.9rem">
                        <span class="fw-700">💰 Economia</span>
                        <span id="antEconValor" class="fw-700" style="color:var(--emerald)">—</span>
                    </div>
                    <div class="text-xs text-muted" style="margin-top:.2rem" id="antEconPct"></div>
                </div>
                <!-- Sem desconto -->
                <div id="antIgualWrap" style="border-top:1px solid var(--border);margin-top:.5rem;padding-top:.6rem;display:none">
                    <div class="text-xs text-muted">Valor igual ao original — sem desconto na antecipação</div>
                </div>
                <!-- Pagando mais -->
                <div id="antSobraWrap" style="border-top:1px solid var(--border);margin-top:.5rem;padding-top:.6rem;display:none">
                    <div style="display:flex;justify-content:space-between;font-size:.85rem">
                        <span class="text-rose">Acima do valor original</span>
                        <span id="antSobraValor" class="fw-600 text-rose">—</span>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top:1.25rem">
                <label class="form-label">Conta de débito</label>
                <select id="antConta" class="form-control">
                    <option value="">— Não débitar —</option>
                    <?php foreach ($contasDes as $ct): ?>
                    <option value="<?= $ct['id'] ?>"><?= htmlspecialchars($ct['nome']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="fecharModalAntecipar()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnConfirmarAnt" onclick="confirmarAntecipacao()">
                <i class="fa-solid fa-circle-check"></i> Confirmar
            </button>
        </div>
    </div>
</div>
