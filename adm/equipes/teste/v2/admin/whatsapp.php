<?php
// admin/whatsapp.php — Próximos aniversariantes + envio via WhatsApp
session_start();
require_once '../includes/auth.php';
auth_guard();
require_once '../conexao.php';

/* ── Calcula próximos aniversários (7 dias) ── */
$stmt = $pdo->query("SELECT nome_completo, data_nascimento, telefone FROM renascer_menbros ORDER BY nome_completo");
$todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hoje     = new DateTime('today');
$proximos = [];
$meses_pt = [
    1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',
    5=>'maio',6=>'junho',7=>'julho',8=>'agosto',
    9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro',
];
foreach ($todos as $m) {
    if (!$m['data_nascimento']) continue;
    try {
        $nasc  = new DateTime($m['data_nascimento']);
        $aniv  = DateTime::createFromFormat('Y-m-d', "{$hoje->format('Y')}-{$nasc->format('m-d')}");
        if (!$aniv) continue;
        if ($aniv < $hoje) $aniv->modify('+1 year');
        $dias = (int)$hoje->diff($aniv)->days;
        if ($dias <= 7) {
            $data_full = $aniv->format('j') . ' de ' . $meses_pt[(int)$aniv->format('n')];
            $proximos[] = [
                'nome'      => $m['nome_completo'],
                'telefone'  => $m['telefone'],
                'data_aniv' => $aniv->format('d/m'),
                'data_full' => $data_full,
                'dias'      => $dias,
            ];
        }
    } catch (Exception $e) { continue; }
}
usort($proximos, fn($a, $b) => $a['dias'] <=> $b['dias']);

function formatarWa(string $tel): ?string
{
    $n = preg_replace('/\D/', '', $tel);
    if (strlen($n) === 10 || strlen($n) === 11) return "55{$n}";
    if (strlen($n) >= 12 && str_starts_with($n, '55')) return $n;
    return null;
}

/* Membros com telefone válido (para envio em massa) */
$paraEnviar  = array_values(array_filter($proximos, fn($p) => $p['telefone'] && formatarWa($p['telefone'])));
$totalComTel = count($paraEnviar);

/* Número de controle padrão */
$numeroControle = '65999892901';

/* ── Mensagem de resumo para o painel de teste ── */
if (!empty($proximos)) {
    $msg_teste = "🎂 *Aniversários — próximos 7 dias*\n\n";
    foreach ($proximos as $p) {
        $quando = $p['dias'] === 0 ? 'Hoje!' : ($p['dias'] === 1 ? 'Amanhã' : "em {$p['dias']} dias");
        $msg_teste .= "{$p['nome']}\n🎂 {$p['data_aniv']} — {$quando}\n\n";
    }
    $msg_teste = rtrim($msg_teste);
} else {
    $msg_teste = "🎂 Nenhum aniversariante nos próximos 7 dias.";
}

$titulo_pagina = 'WhatsApp Aniversários - Igreja Renascer';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<main class="dashboard">
    <div class="topbar">
        <h1>&#128242; WhatsApp — Aniversários dos próximos 7 dias</h1>
        <div class="topbar-actions">
            <a href="aniversarios.php" class="btn btn-secondary btn-sm">&#127881; Calendário Completo</a>
            <?php if ($totalComTel > 0): ?>
            <button class="btn btn-green btn-sm" onclick="abrirModalMassa()">
                &#128640; Enviar para Todos (<?= $totalComTel ?>)
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ PAINEL DE TESTE ══════════════════════════════════════ -->
    <div class="teste-box" id="testeBox">
        <div class="teste-header" onclick="toggleTeste()">
            <span>&#129514; Enviar Mensagem de Teste</span>
            <span class="teste-chevron" id="testeChevron">&#9660;</span>
        </div>
        <div class="teste-body" id="testeBody">
            <p class="teste-descricao">
                Edite o número e o texto abaixo e clique em <strong>Enviar Teste</strong>
                para verificar se a integração está funcionando antes de usar com membros reais.
            </p>
            <div class="form-grid-2" style="margin-bottom:.8rem;">
                <div class="form-group" style="margin-bottom:0">
                    <label for="testeNumero">Número de destino</label>
                    <input type="text" id="testeNumero" class="form-control"
                           value="<?= htmlspecialchars($numeroControle) ?>"
                           placeholder="(65) 99999-9999" autocomplete="off">
                </div>
                <div class="form-group" style="margin-bottom:0;display:flex;flex-direction:column;justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <button class="btn btn-green" id="btnTeste" onclick="enviarTeste()">
                        &#9654; Enviar Teste
                    </button>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:.8rem;">
                <label for="testeMensagem">Mensagem
                    <small style="color:var(--text-muted);font-weight:400;">— edite livremente antes de enviar</small>
                </label>
                <textarea id="testeMensagem" class="form-control" rows="<?= max(4, count($proximos) * 3 + 2) ?>"><?= htmlspecialchars($msg_teste) ?></textarea>
            </div>
            <div id="testeResultado" style="display:none;"></div>
        </div>
    </div>

    <!-- ══ LISTA DE ANIVERSARIANTES (7 dias) ════════════════════ -->
    <?php if (empty($proximos)): ?>
        <div class="alert alert-sucesso" style="margin-top:16px;">
            &#127881; Nenhum aniversariante nos próximos 7 dias.
        </div>
    <?php else: ?>
        <p style="color:var(--text-2);font-size:.88em;margin:0 0 14px;">
            <strong><?= count($proximos) ?></strong>
            aniversariante<?= count($proximos) !== 1 ? 's' : '' ?> nos próximos 7 dias.
            <?php if ($totalComTel < count($proximos)): ?>
                <span style="color:var(--text-muted);">(<?= count($proximos) - $totalComTel ?> sem telefone cadastrado)</span>
            <?php endif; ?>
        </p>
        <div class="wa-grid">
            <?php foreach ($proximos as $p):
                $waNum  = $p['telefone'] ? formatarWa($p['telefone']) : null;
                $msg    = "🎂 {$p['nome']} faz aniversário dia {$p['data_full']}!";
                $waLink = $waNum ? "https://wa.me/{$waNum}?text=" . rawurlencode($msg) : null;
                $ehHoje = $p['dias'] === 0;
                $urgente= $p['dias'] <= 2;
            ?>
            <div class="wa-card <?= $ehHoje ? 'wa-hoje-card' : ($urgente ? 'wa-urgente' : '') ?>"
                 data-telefone="<?= htmlspecialchars($p['telefone'] ?? '', ENT_QUOTES) ?>"
                 data-nome="<?= htmlspecialchars($p['nome'], ENT_QUOTES) ?>"
                 data-data="<?= htmlspecialchars($p['data_full'], ENT_QUOTES) ?>"
                 data-msg="<?= htmlspecialchars($msg, ENT_QUOTES) ?>">
                <div class="wa-info">
                    <div class="wa-nome"><?= htmlspecialchars($p['nome']) ?></div>
                    <div class="wa-data">
                        &#127874; <?= $p['data_aniv'] ?>
                        <?php if ($ehHoje): ?>
                            <span class="wa-badge-hoje">Hoje!</span>
                        <?php elseif ($p['dias'] === 1): ?>
                            <span class="wa-badge-amanha">Amanhã</span>
                        <?php else: ?>
                            &mdash; em <?= $p['dias'] ?> dias
                        <?php endif; ?>
                    </div>
                    <?php if ($p['telefone']): ?>
                        <div class="wa-tel">&#128222; <?= htmlspecialchars($p['telefone']) ?></div>
                    <?php else: ?>
                        <div class="wa-sem-tel">Sem telefone cadastrado</div>
                    <?php endif; ?>
                    <div class="wa-msg-preview">&#128172; <?= htmlspecialchars($msg) ?></div>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
                    <?php if ($waLink): ?>
                        <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener" class="btn wa-btn" title="Abrir no WhatsApp">
                            <svg width="15" height="15" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true"><path d="M16 0C7.163 0 0 7.163 0 16c0 2.833.74 5.494 2.034 7.81L.054 31.946l8.344-2.13A15.94 15.94 0 0016 32c8.837 0 16-7.163 16-16S24.837 0 16 0zm0 29.5a13.45 13.45 0 01-6.854-1.878l-.49-.291-5.087 1.297 1.32-4.822-.32-.495A13.5 13.5 0 1116 29.5z"/><path d="M23.472 19.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.174.199-.347.223-.645.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.652-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.447-.52.148-.173.197-.297.297-.497.1-.198.05-.372-.025-.52-.074-.149-.669-1.611-.916-2.207-.242-.579-.487-.5-.669-.51-.174-.008-.372-.01-.57-.01-.198 0-.52.074-.793.372-.272.298-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.263.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.412.249-.694.249-1.29.174-1.414-.074-.124-.273-.198-.57-.347z"/></svg>
                            Abrir WA
                        </a>
                        <button class="btn btn-secondary btn-sm"
                                onclick="enviarDireto(<?= htmlspecialchars(json_encode($p['telefone'])) ?>, <?= htmlspecialchars(json_encode($msg)) ?>, this)"
                                title="Enviar via integração ApiBrasil">
                            Enviar Direto
                        </button>
                    <?php else: ?>
                        <div class="wa-sem-btn">Sem WhatsApp</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- ══ MODAL ENVIO EM MASSA ══════════════════════════════════════ -->
<div class="modal-overlay" id="modalMassaOverlay">
    <div class="modal" style="max-width:540px;" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h3>&#128640; Envio em Massa — Próximos 7 dias</h3>
            <button class="modal-close" onclick="fecharModalMassa()" id="btnFecharMassa">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Fase 1: Configuração e confirmação -->
            <div id="massaFase1">

                <!-- Número de controle editável -->
                <div class="massa-config-box">
                    <label class="massa-config-label" for="massaNumControle">
                        &#128222; Número de controle <span style="color:var(--text-muted);font-weight:400;">(recebe o relatório)</span>
                    </label>
                    <input type="text" id="massaNumControle" class="form-control"
                           value="<?= htmlspecialchars($numeroControle) ?>"
                           placeholder="65999999999">
                </div>

                <div class="form-group" style="margin-top:14px;">
                    <label for="massaMensagem">
                        Mensagem padrão
                        <small style="color:var(--text-muted);font-weight:400;">— use <code>{nome}</code> e <code>{data}</code></small>
                    </label>
                    <textarea id="massaMensagem" class="form-control" rows="4">🎂 {nome} faz aniversário dia {data}!</textarea>
                </div>

                <p style="color:var(--text-2);font-size:.87em;margin:12px 0 8px;">
                    Destinatários com telefone válido: <strong id="massaTotal">0</strong>
                </p>
                <div class="massa-lista" id="massaLista"></div>
            </div>

            <!-- Fase 2: Progresso -->
            <div id="massaFase2" style="display:none;">
                <div class="massa-progress-wrap">
                    <div class="massa-progress-bar-outer">
                        <div class="massa-progress-bar-inner" id="massaProgressBar" style="width:0%"></div>
                    </div>
                    <div class="massa-progress-texto" id="massaProgressTexto">Preparando…</div>
                </div>
                <div class="massa-log" id="massaLog"></div>
            </div>

            <!-- Fase 3: Resultado -->
            <div id="massaFase3" style="display:none;">
                <div id="massaResultado"></div>
            </div>
        </div>
        <div class="modal-footer">
            <div class="modal-footer-actions" style="width:100%;justify-content:space-between;">
                <button class="btn btn-secondary" id="btnCancelarMassa" onclick="fecharModalMassa()">Cancelar</button>
                <button class="btn btn-green" id="btnIniciarMassa" onclick="iniciarEnvioMassa()">
                    &#128640; Iniciar Envio
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.teste-box {
    background: var(--surface);
    border: 1.5px solid var(--primary);
    border-radius: 10px;
    margin-bottom: 22px;
    overflow: hidden;
    box-shadow: var(--shadow);
}
.teste-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 20px;
    cursor: pointer;
    font-weight: 700;
    font-size: .97em;
    color: var(--text);
    user-select: none;
    transition: background .15s;
}
.teste-header:hover { background: var(--surface-2); }
.teste-chevron { font-size: .85em; transition: transform .25s; }
.teste-chevron.fechado { transform: rotate(-90deg); }
.teste-body { padding: 0 20px 18px; border-top: 1px solid var(--border); }
.teste-descricao { color: var(--text-2); font-size: .87em; margin: 12px 0 14px; }

/* Config box no modal */
.massa-config-box {
    background: var(--surface-2);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 12px 14px;
}
.massa-config-label {
    display: block;
    font-weight: 700;
    font-size: .85em;
    color: var(--text-2);
    margin-bottom: 6px;
}

.massa-lista {
    max-height: 160px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 7px;
    padding: 6px 10px;
    background: var(--surface-2);
}
.massa-lista-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid var(--border);
    font-size: .85em;
    color: var(--text);
}
.massa-lista-item:last-child { border-bottom: none; }
.massa-lista-item .ml-nome { flex: 1; font-weight: 600; }
.massa-lista-item .ml-tel  { color: var(--text-2); font-size: .83em; }

.massa-progress-wrap { margin: 10px 0 16px; }
.massa-progress-bar-outer {
    height: 10px;
    background: var(--surface-2);
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--border);
}
.massa-progress-bar-inner {
    height: 100%;
    background: #25d366;
    border-radius: 10px;
    transition: width .3s ease;
}
.massa-progress-texto { text-align:center; font-size:.86em; color:var(--text-2); margin-top:6px; }
.massa-log {
    max-height: 200px;
    overflow-y: auto;
    font-size: .82em;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 7px;
    padding: 8px 12px;
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.massa-log p { margin: 0; }
.massa-log-ok   { color: var(--ok); }
.massa-log-erro { color: var(--err); }
.massa-log-info { color: var(--text-2); }
</style>

<script>
var _testeAberto = true;
function toggleTeste() {
    _testeAberto = !_testeAberto;
    document.getElementById('testeBody').style.display = _testeAberto ? '' : 'none';
    document.getElementById('testeChevron').classList.toggle('fechado', !_testeAberto);
}

async function enviarTeste() {
    var numero   = document.getElementById('testeNumero').value.trim();
    var mensagem = document.getElementById('testeMensagem').value.trim();
    var resultado = document.getElementById('testeResultado');
    if (!numero)   { _testeMsg('Informe o número de destino.', 'erro', resultado); return; }
    if (!mensagem) { _testeMsg('A mensagem não pode estar vazia.', 'erro', resultado); return; }

    var btn = document.getElementById('btnTeste');
    btn.disabled = true; btn.textContent = '⏳ Enviando…';
    try {
        var fd = new FormData();
        fd.append('numero',   numero);
        fd.append('mensagem', mensagem);
        var r    = await fetch('../whatsapp/enviar_mensagem.php', { method: 'POST', body: fd });
        var data = await r.json();
        _testeMsg(data.msg, data.ok ? 'sucesso' : 'erro', resultado);
    } catch (e) {
        _testeMsg('Erro de conexão: ' + e.message, 'erro', resultado);
    }
    btn.disabled = false; btn.textContent = '▶ Enviar Teste';
}

async function enviarDireto(telefone, mensagem, btn) {
    if (!confirm('Enviar mensagem para ' + telefone + ' via integração?')) return;
    var original = btn.textContent;
    btn.disabled = true; btn.textContent = '⏳…';
    try {
        var fd = new FormData();
        fd.append('numero',   telefone);
        fd.append('mensagem', mensagem);
        var r    = await fetch('../whatsapp/enviar_mensagem.php', { method: 'POST', body: fd });
        var data = await r.json();
        if (data.ok) {
            btn.textContent = '✅ Enviado';
            btn.classList.replace('btn-secondary', 'btn-green');
        } else {
            alert('Falha: ' + data.msg);
            btn.disabled = false; btn.textContent = original;
        }
    } catch (e) {
        alert('Erro de conexão: ' + e.message);
        btn.disabled = false; btn.textContent = original;
    }
}

function _testeMsg(texto, tipo, el) {
    el.className = 'alert alert-' + tipo;
    el.textContent = texto;
    el.style.display = 'block';
}

/* ================================================================
   ENVIO EM MASSA
   ================================================================ */
var _massaOverlay  = document.getElementById('modalMassaOverlay');
var _massaContatos = [];

function abrirModalMassa() {
    _massaContatos = [];
    document.querySelectorAll('.wa-card[data-telefone]').forEach(function (card) {
        var tel = (card.dataset.telefone || '').trim();
        if (!tel) return;
        _massaContatos.push({
            nome:      card.dataset.nome  || '',
            telefone:  tel,
            data:      card.dataset.data  || '',
        });
    });

    document.getElementById('massaTotal').textContent = _massaContatos.length;

    var lista = document.getElementById('massaLista');
    lista.innerHTML = '';
    _massaContatos.forEach(function (c) {
        var div = document.createElement('div');
        div.className = 'massa-lista-item';
        div.innerHTML =
            '<span class="ml-nome">' + _esc(c.nome) + '</span>' +
            '<span class="ml-tel">'  + _esc(c.telefone) + '</span>';
        lista.appendChild(div);
    });

    document.getElementById('massaFase1').style.display = '';
    document.getElementById('massaFase2').style.display = 'none';
    document.getElementById('massaFase3').style.display = 'none';
    document.getElementById('btnIniciarMassa').style.display = '';
    document.getElementById('btnCancelarMassa').textContent = 'Cancelar';
    document.getElementById('btnCancelarMassa').onclick = fecharModalMassa;
    document.getElementById('btnCancelarMassa').disabled = false;
    document.getElementById('btnFecharMassa').style.display = '';

    _massaOverlay.classList.add('ativo');
}

function fecharModalMassa() { _massaOverlay.classList.remove('ativo'); }
_massaOverlay.addEventListener('click', function (e) {
    if (e.target === _massaOverlay) fecharModalMassa();
});

var _massaCancelado = false;

async function iniciarEnvioMassa() {
    if (_massaContatos.length === 0) return;

    var template    = document.getElementById('massaMensagem').value.trim();
    var numControle = document.getElementById('massaNumControle').value.trim();
    if (!template)    { alert('Escreva a mensagem antes de enviar.'); return; }
    if (!numControle) { alert('Informe o número de controle.'); return; }
    if (!confirm('Confirma o envio para ' + _massaContatos.length + ' contato(s)?')) return;

    _massaCancelado = false;

    document.getElementById('massaFase1').style.display = 'none';
    document.getElementById('massaFase2').style.display = '';
    document.getElementById('btnIniciarMassa').style.display = 'none';
    document.getElementById('btnCancelarMassa').textContent = '⏹ Parar';
    document.getElementById('btnCancelarMassa').onclick = function () {
        _massaCancelado = true;
        this.disabled = true;
        this.textContent = 'Parando…';
    };

    var log   = document.getElementById('massaLog');
    var bar   = document.getElementById('massaProgressBar');
    var texto = document.getElementById('massaProgressTexto');
    log.innerHTML = '';

    var total    = _massaContatos.length;
    var enviados = 0;
    var falhas   = 0;

    function logLinha(msg, cls) {
        var p = document.createElement('p');
        p.className = cls;
        p.textContent = msg;
        log.appendChild(p);
        log.scrollTop = log.scrollHeight;
    }

    logLinha('🚀 Iniciando envio para ' + total + ' contato(s)…', 'massa-log-info');

    for (var i = 0; i < _massaContatos.length; i++) {
        if (_massaCancelado) {
            logLinha('⏹ Envio interrompido pelo operador.', 'massa-log-erro');
            break;
        }

        var c  = _massaContatos[i];
        var msg = template
            .replace(/\{nome\}/gi, c.nome)
            .replace(/\{data\}/gi, c.data);

        texto.textContent = 'Enviando ' + (i + 1) + ' de ' + total + ': ' + c.nome + '…';
        bar.style.width   = (((i + 1) / total) * 100) + '%';

        try {
            var fd = new FormData();
            fd.append('numero',   c.telefone);
            fd.append('mensagem', msg);
            var r    = await fetch('../whatsapp/enviar_mensagem.php', { method: 'POST', body: fd });
            var data = await r.json();
            if (data.ok) {
                enviados++;
                logLinha('✅ ' + c.nome + ' (' + c.telefone + ')', 'massa-log-ok');
            } else {
                falhas++;
                logLinha('❌ ' + c.nome + ': ' + data.msg, 'massa-log-erro');
            }
        } catch (e) {
            falhas++;
            logLinha('❌ ' + c.nome + ': Erro de conexão', 'massa-log-erro');
        }

        if (i < _massaContatos.length - 1 && !_massaCancelado) {
            await new Promise(function (res) { setTimeout(res, 1500); });
        }
    }

    // Relatório para número de controle
    logLinha('📊 Enviando relatório para ' + numControle + '…', 'massa-log-info');
    try {
        var fdRel = new FormData();
        fdRel.append('total',         total);
        fdRel.append('enviados',      enviados);
        fdRel.append('falhas',        falhas);
        fdRel.append('num_controle',  numControle);
        await fetch('../whatsapp/enviar_massa.php', { method: 'POST', body: fdRel });
    } catch (_) {}

    bar.style.width   = '100%';
    texto.textContent = 'Concluído!';

    document.getElementById('massaFase2').style.display = 'none';
    document.getElementById('massaFase3').style.display = '';
    document.getElementById('btnCancelarMassa').style.display = 'none';
    document.getElementById('btnFecharMassa').style.display   = '';

    var corRes  = falhas === 0 ? 'sucesso' : 'erro';
    var iconRes = falhas === 0 ? '🎉' : '⚠️';
    document.getElementById('massaResultado').innerHTML =
        '<div class="alert alert-' + corRes + '" style="margin:0;">' +
        iconRes + ' <strong>Envio concluído!</strong><br>' +
        '✅ Enviados: <strong>' + enviados + '</strong> &nbsp;|&nbsp; ' +
        '❌ Falhas: <strong>' + falhas + '</strong> &nbsp;|&nbsp; ' +
        '📊 Total: <strong>' + total + '</strong><br>' +
        '<small style="opacity:.8;">Relatório enviado para ' + _esc(numControle) + '.</small>' +
        '</div>';
}

function _esc(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}
</script>

<?php require_once '../includes/footer.php'; ?>
