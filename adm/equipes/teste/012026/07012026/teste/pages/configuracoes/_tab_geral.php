<?php
// ============================================================
// _tab_geral.php — Aba "Geral" de Configurações.
// Partial de pages/configuracoes.php — usa $cfg do arquivo pai.
// ============================================================
?>
<div id="cfg-geral" class="cfg-pane active">

    <div class="cfg-section">
        <div class="cfg-section-title">Identificação</div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Nome do usuário</div>
                <div class="cfg-field-desc">Exibido no cabeçalho e relatórios</div>
            </div>
            <div class="cfg-field-input">
                <input type="text" id="cfgNome" class="form-control"
                       value="<?= htmlspecialchars($cfg['usuario_nome'] ?? '') ?>"
                       placeholder="Seu nome">
            </div>
        </div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">E-mail</div>
                <div class="cfg-field-desc">Para notificações futuras</div>
            </div>
            <div class="cfg-field-input">
                <input type="email" id="cfgEmail" class="form-control"
                       value="<?= htmlspecialchars($cfg['usuario_email'] ?? '') ?>"
                       placeholder="seu@email.com">
            </div>
        </div>
    </div>

    <div class="cfg-section">
        <div class="cfg-section-title">Finanças</div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Moeda padrão</div>
                <div class="cfg-field-desc">Símbolo usado em toda a interface</div>
            </div>
            <div class="cfg-field-input">
                <select id="cfgMoeda" class="form-control">
                    <option value="BRL" <?= ($cfg['moeda'] ?? 'BRL') === 'BRL' ? 'selected' : '' ?>>BRL — Real Brasileiro (R$)</option>
                    <option value="USD" <?= ($cfg['moeda'] ?? '') === 'USD' ? 'selected' : '' ?>>USD — Dólar Americano ($)</option>
                    <option value="EUR" <?= ($cfg['moeda'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR — Euro (€)</option>
                </select>
            </div>
        </div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Dia de fechamento padrão</div>
                <div class="cfg-field-desc">Dia padrão para fechamento de cartões</div>
            </div>
            <div class="cfg-field-input">
                <input type="number" id="cfgDiaFech" class="form-control"
                       value="<?= htmlspecialchars($cfg['dia_fechamento_padrao'] ?? '5') ?>"
                       min="1" max="31" style="max-width:90px">
            </div>
        </div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Meta de poupança (%)</div>
                <div class="cfg-field-desc">Percentual alvo de poupança mensal (padrão 20%)</div>
            </div>
            <div class="cfg-field-input">
                <input type="number" id="cfgMetaPoupanca" class="form-control"
                       value="<?= htmlspecialchars($cfg['meta_poupanca'] ?? '20') ?>"
                       min="0" max="100" step="1" style="max-width:90px">
            </div>
        </div>
    </div>

    <div class="cfg-section">
        <div class="cfg-section-title">Alertas</div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Alertar limite de cartão (%)</div>
                <div class="cfg-field-desc">Cria alerta quando o uso do cartão atingir este percentual</div>
            </div>
            <div class="cfg-field-input">
                <input type="number" id="cfgAlertaCartao" class="form-control"
                       value="<?= htmlspecialchars($cfg['alerta_cartao_pct'] ?? '80') ?>"
                       min="10" max="100" step="5" style="max-width:90px">
            </div>
        </div>

        <div class="cfg-field">
            <div>
                <div class="cfg-field-label">Dias de antecedência — vencimentos</div>
                <div class="cfg-field-desc">Quantos dias antes alertar sobre parcelas e contas a vencer</div>
            </div>
            <div class="cfg-field-input">
                <input type="number" id="cfgAlertaDias" class="form-control"
                       value="<?= htmlspecialchars($cfg['alerta_dias_antecedencia'] ?? '5') ?>"
                       min="1" max="30" style="max-width:90px">
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:.75rem">
        <button class="btn btn-primary" onclick="salvarConfigs()">
            <i class="fa-solid fa-floppy-disk"></i> Salvar configurações
        </button>
    </div>
</div>
