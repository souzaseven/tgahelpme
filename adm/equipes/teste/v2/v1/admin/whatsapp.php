<?php
// admin/whatsapp.php — Próximos aniversariantes + envio via WhatsApp
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'maiara') {
    header('Location: login.php');
    exit;
}
require_once '../conexao.php';

/* ── Calcula próximos aniversários (30 dias) ── */
$stmt = $pdo->query("SELECT nome_completo, data_nascimento, telefone FROM renascer_menbros ORDER BY nome_completo");
$todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hoje     = new DateTime('today');
$proximos = [];
foreach ($todos as $m) {
    if (!$m['data_nascimento']) continue;
    try {
        $nasc  = new DateTime($m['data_nascimento']);
        $aniv  = DateTime::createFromFormat('Y-m-d', "{$hoje->format('Y')}-{$nasc->format('m-d')}");
        if (!$aniv) continue;
        if ($aniv < $hoje) $aniv->modify('+1 year');
        $dias = (int)$hoje->diff($aniv)->days;
        if ($dias <= 30) {
            $proximos[] = [
                'nome'      => $m['nome_completo'],
                'telefone'  => $m['telefone'],
                'data_aniv' => $aniv->format('d/m'),
                'dias'      => $dias,
            ];
        }
    } catch (Exception $e) { continue; }
}
usort($proximos, fn($a, $b) => $a['dias'] <=> $b['dias']);

function formatarWa(string $tel): ?string
{
    $n = preg_replace('/\D/', '', $tel);
    if (strlen($n) === 10 || strlen($n) === 11) return "55$n";
    if (strlen($n) >= 12 && str_starts_with($n, '55')) return $n;
    return null;
}

$titulo_pagina = 'WhatsApp Aniversários - Igreja Renascer';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<main class="dashboard">
    <div class="topbar">
        <h1>&#128242; WhatsApp — Aniversários</h1>
        <div class="topbar-actions">
            <a href="aniversarios.php" class="btn btn-secondary btn-sm">Ver Calendário</a>
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
                           placeholder="(11) 99999-9999" autocomplete="off">
                </div>
                <div class="form-group" style="margin-bottom:0;display:flex;flex-direction:column;justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <button class="btn btn-green" id="btnTeste" onclick="enviarTeste()">
                        &#9654; Enviar Teste
                    </button>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:.8rem;">
                <label for="testeMensagem">Mensagem</label>
                <textarea id="testeMensagem" class="form-control" rows="5">Olá, [Nome]! 🎂✨
A Igreja Renascer deseja um feliz aniversário!
Que Deus abençoe muito a sua vida! 🙏
Parabéns! 🎉</textarea>
            </div>
            <div id="testeResultado" style="display:none;"></div>
        </div>
    </div>

    <!-- ══ LISTA DE ANIVERSARIANTES ════════════════════════════ -->
    <?php if (empty($proximos)): ?>
        <div class="alert alert-sucesso" style="margin-top:16px;">
            Nenhum aniversariante nos próximos 30 dias. &#127881;
        </div>
    <?php else: ?>
        <p style="color:var(--text-2);font-size:.88em;margin:16px 0 12px;">
            <strong><?= count($proximos) ?></strong>
            aniversariante<?= count($proximos) !== 1 ? 's' : '' ?> nos próximos 30 dias.
            Clique em <strong>Abrir WA</strong> para enviar pelo seu WhatsApp,
            ou em <strong>Enviar Direto</strong> para usar a integração.
        </p>
        <div class="wa-grid">
            <?php foreach ($proximos as $p):
                $waNum  = $p['telefone'] ? formatarWa($p['telefone']) : null;
                $msg    = "Olá, {$p['nome']}! 🎂✨\nA Igreja Renascer deseja um feliz aniversário!\nQue Deus abençoe muito a sua vida! 🙏\nParabéns! 🎉";
                $waLink = $waNum ? "https://wa.me/{$waNum}?text=" . rawurlencode($msg) : null;
                $ehHoje = $p['dias'] === 0;
                $urgente= $p['dias'] <= 7;
            ?>
            <div class="wa-card <?= $ehHoje ? 'wa-hoje-card' : ($urgente ? 'wa-urgente' : '') ?>">
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

<style>
/* ── Painel de Teste ── */
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
.teste-body {
    padding: 0 20px 18px;
    border-top: 1px solid var(--border);
}
.teste-descricao {
    color: var(--text-2);
    font-size: .87em;
    margin: 12px 0 14px;
}
</style>

<script>
/* ── Toggle painel de teste ── */
var _testeAberto = true;
function toggleTeste() {
    _testeAberto = !_testeAberto;
    document.getElementById('testeBody').style.display    = _testeAberto ? '' : 'none';
    document.getElementById('testeChevron').classList.toggle('fechado', !_testeAberto);
}

/* ── Enviar TESTE (número + mensagem editáveis) ── */
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

/* ── Enviar DIRETO para um aniversariante ── */
async function enviarDireto(telefone, mensagem, btn) {
    if (!confirm('Enviar mensagem de aniversário para ' + telefone + ' via integração?')) return;

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

/* ── Helper: exibe resultado do teste ── */
function _testeMsg(texto, tipo, el) {
    el.className     = 'alert alert-' + tipo;
    el.textContent   = texto;
    el.style.display = 'block';
}
</script>

<?php require_once '../includes/footer.php'; ?>
