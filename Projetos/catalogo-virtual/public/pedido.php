<?php
require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo      = 'Finalizar Pedido';
$sessao      = $_SESSION['sessao_carrinho'] ?? '';
$itens       = [];
$total       = 0;
$carrinho_id = null;

if (!empty($sessao)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM carrinho WHERE sessao = ?");
        $stmt->execute([$sessao]);
        $carrinho = $stmt->fetch();

        if ($carrinho) {
            $carrinho_id = $carrinho['id'];
            $stmt = $pdo->prepare("
                SELECT ci.id, ci.quantidade, ci.preco_unitario,
                       p.id AS produto_id, p.nome, p.codigo, p.foto,
                       (ci.quantidade * ci.preco_unitario) AS subtotal
                FROM carrinho_itens ci
                INNER JOIN produtos p ON p.id = ci.produto_id
                WHERE ci.carrinho_id = ?
            ");
            $stmt->execute([$carrinho_id]);
            $itens = $stmt->fetchAll();
            $total = array_sum(array_column($itens, 'subtotal'));
        }
    } catch (PDOException $e) {
        $itens = [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Pedido — Catálogo Virtual</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="header-principal">
    <div class="container">
        <a href="index.php" class="logo">
            <div class="logo-icon">📦</div>
            <h1>Catálogo</h1>
        </a>
        <div class="header-busca">
            <form method="GET" action="index.php">
                <div class="header-busca-inner">
                    <span class="icone-busca">🔍</span>
                    <input type="text" name="busca" placeholder="Buscar produtos..." autocomplete="off">
                </div>
            </form>
        </div>
        <nav class="header-nav">
            <a href="../admin/index.php" class="nav-link">⚙️ Admin</a>
            <a href="carrinho.php" class="nav-link nav-carrinho">
                🛒 Carrinho
                <span class="badge-contador" id="contador-carrinho">
                    <?= count($itens) ?>
                </span>
            </a>
        </nav>
    </div>
</header>

<main class="pagina-pedido">
    <div class="container">

    <?php if (empty($itens)): ?>

        <!-- CARRINHO VAZIO -->
        <div class="central-wrapper">
            <div class="central-card">
                <div class="central-icone" style="opacity:.25">🛒</div>
                <h2>Carrinho vazio</h2>
                <p>Adicione produtos antes de finalizar o pedido.</p>
                <a href="index.php" class="btn btn-primary" style="margin-top:8px;">Ver Catálogo</a>
            </div>
        </div>

    <?php else: ?>

        <!-- TOPO -->
        <div class="pedido-topo">
            <div class="topo-left">
                <a href="carrinho.php" class="btn-voltar">← Voltar ao Carrinho</a>
                <h2>Finalizar Pedido</h2>
            </div>
            <div class="pedido-steps">
                <div class="step concluido">
                    <div class="step-num">✓</div>
                    <span class="step-label">Carrinho</span>
                </div>
                <div class="step-linha"></div>
                <div class="step ativo" id="step-dados">
                    <div class="step-num">2</div>
                    <span class="step-label">Seus Dados</span>
                </div>
                <div class="step-linha"></div>
                <div class="step" id="step-confirmado">
                    <div class="step-num">3</div>
                    <span class="step-label">Confirmado</span>
                </div>
            </div>
        </div>

        <!-- LAYOUT PRINCIPAL -->
        <div class="pedido-layout" id="pedido-layout">

            <!-- RESUMO -->
            <div class="pedido-resumo-col">
                <div class="p-card">
                    <div class="p-card-header">
                        <span>🛒</span>
                        <h3>Resumo do Pedido</h3>
                        <span class="badge-count"><?= count($itens) ?> item<?= count($itens) > 1 ? 's' : '' ?></span>
                    </div>

                    <div class="itens-lista">
                        <?php foreach ($itens as $item): ?>
                            <div class="item-row">
                                <div class="item-foto">
                                    <?php if (!empty($item['foto'])): ?>
                                        <img src="<?= htmlspecialchars($item['foto']) ?>"
                                             alt="<?= htmlspecialchars($item['nome']) ?>"
                                             onerror="this.style.display='none'">
                                    <?php else: ?>
                                        📦
                                    <?php endif; ?>
                                </div>
                                <div class="item-info">
                                    <span class="item-nome"><?= htmlspecialchars($item['nome']) ?></span>
                                    <span class="item-qtd">
                                        <?= (int)$item['quantidade'] ?>×
                                        R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?>
                                    </span>
                                </div>
                                <strong class="item-sub">
                                    R$ <?= number_format($item['subtotal'], 2, ',', '.') ?>
                                </strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="total-bar">
                        <span>Total</span>
                        <strong>R$ <?= number_format($total, 2, ',', '.') ?></strong>
                    </div>
                </div>

                <a href="carrinho.php" class="link-editar">✏️ Editar carrinho</a>
            </div>

            <!-- FORMULÁRIO -->
            <div class="pedido-form-col">
                <div class="p-card">
                    <div class="p-card-header">
                        <span>👤</span>
                        <h3>Seus Dados</h3>
                    </div>

                    <div class="p-card-body">
                        <div class="form-grupo">
                            <label for="cliente_nome">
                                Nome completo <span class="obrigatorio">*</span>
                            </label>
                            <input type="text" id="cliente_nome"
                                   placeholder="Como devemos te chamar?"
                                   autocomplete="name">
                        </div>

                        <div class="form-linha-dupla">
                            <div class="form-grupo">
                                <label for="cliente_telefone">📱 Telefone</label>
                                <input type="tel" id="cliente_telefone"
                                       placeholder="(65) 99999-0000"
                                       autocomplete="tel">
                            </div>
                            <div class="form-grupo">
                                <label for="cliente_email">✉️ E-mail</label>
                                <input type="email" id="cliente_email"
                                       placeholder="seu@email.com"
                                       autocomplete="email">
                            </div>
                        </div>

                        <div class="form-grupo">
                            <label for="observacao">📝 Observação</label>
                            <textarea id="observacao" rows="3"
                                      placeholder="Alguma observação? (opcional)"></textarea>
                        </div>

                        <div class="aviso-contato">
                            💡 Informe telefone ou e-mail para podermos confirmar seu pedido.
                        </div>

                        <button class="btn btn-success btn-confirmar" id="btn-confirmar"
                                onclick="finalizarPedido()">
                            ✅ Confirmar Pedido —
                            R$ <?= number_format($total, 2, ',', '.') ?>
                        </button>

                        <p class="txt-seguranca">🔒 Dados usados apenas para contato sobre este pedido.</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- TELA DE SUCESSO -->
        <div id="tela-sucesso" style="display:none">
            <div class="central-wrapper">
                <div class="central-card sucesso">

                    <div class="sucesso-check">✅</div>
                    <h2>Pedido Confirmado!</h2>
                    <p id="txt-pedido-num" class="sucesso-num"></p>
                    <p class="sucesso-sub">Em breve entraremos em contato para confirmar os detalhes.</p>

                    <div class="sucesso-acoes">
                        <button class="btn btn-primary" onclick="window.print()">
                            🖨️ Imprimir Comprovante
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            🛍️ Continuar Comprando
                        </a>
                    </div>

                </div>
            </div>

            <!-- COMPROVANTE (visível apenas na impressão) -->
            <div id="comprovante-print" class="comprovante-print">
                <div class="comprovante-header">
                    <h2>📦 Catálogo Virtual</h2>
                    <h3>Comprovante de Pedido</h3>
                </div>
                <div id="comprovante-body"></div>
            </div>
        </div>

    <?php endif; ?>

    </div>
</main>

<footer class="footer-principal">
    <div class="container">
        <div class="footer-inner">
            <span class="footer-logo">📦 Catálogo Virtual</span>
            <p>&copy; <?= date('Y') ?> — Todos os direitos reservados</p>
        </div>
    </div>
</footer>

<style>
/* Topo */
.pedido-topo {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 20px;
}

.topo-left { display: flex; flex-direction: column; gap: 4px; }

.pedido-topo h2 {
    font-family: 'DM Serif Display', serif;
    font-size: 1.9rem;
    font-weight: 400;
    color: var(--texto);
    margin-top: 4px;
}

.btn-voltar {
    font-size: 0.78rem;
    color: var(--texto-light);
    text-decoration: none;
    transition: var(--trans);
}
.btn-voltar:hover { color: var(--azul); }

/* Steps */
.pedido-steps {
    display: flex;
    align-items: center;
    gap: 8px;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.step-num {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #E2E8F0;
    color: var(--texto-light);
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--trans);
}

.step.ativo    .step-num { background: var(--azul);  color: #fff; box-shadow: 0 0 0 4px rgba(37,99,235,.15); }
.step.concluido .step-num { background: var(--verde); color: #fff; }
.step.done      .step-num { background: var(--verde); color: #fff; }

.step-label {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--texto-light);
    white-space: nowrap;
}

.step.ativo    .step-label  { color: var(--azul); }
.step.concluido .step-label { color: var(--verde); }
.step.done      .step-label { color: var(--verde); }

.step-linha {
    width: 40px;
    height: 2px;
    background: #E2E8F0;
    border-radius: 2px;
    margin-bottom: 20px;
}

/* Layout */
.pedido-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: start;
}

/* Cards */
.p-card {
    background: #fff;
    border: 1px solid var(--borda);
    border-radius: var(--raio);
    box-shadow: var(--sombra-sm);
    overflow: hidden;
    margin-bottom: 10px;
}

.p-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 20px;
    background: #FAFBFC;
    border-bottom: 1px solid var(--borda);
}

.p-card-header h3 {
    flex: 1;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--texto);
}

.badge-count {
    font-size: 0.72rem;
    font-weight: 700;
    background: #EEF2FF;
    color: var(--azul);
    padding: 3px 10px;
    border-radius: var(--raio-pill);
}

/* Itens */
.itens-lista { padding: 6px 0; }

.item-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid #F8FAFC;
    transition: var(--trans);
}

.item-row:last-child { border-bottom: none; }
.item-row:hover { background: #FAFBFC; }

.item-foto {
    width: 46px;
    height: 46px;
    border-radius: var(--raio-sm);
    background: #F1F5F9;
    border: 1px solid var(--borda);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    overflow: hidden;
    flex-shrink: 0;
}

.item-foto img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}

.item-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.item-nome {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--texto);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.item-qtd {
    font-size: 0.75rem;
    color: var(--texto-light);
}

.item-sub {
    font-size: 0.9rem;
    color: var(--texto);
    flex-shrink: 0;
}

.total-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-top: 2px solid var(--borda);
    background: #F8FAFC;
}

.total-bar span { font-size: 0.875rem; font-weight: 700; color: var(--texto); }
.total-bar strong { font-size: 1.3rem; font-weight: 700; color: var(--verde); letter-spacing: -.5px; }

.link-editar {
    display: block;
    text-align: center;
    font-size: 0.78rem;
    color: var(--texto-light);
    text-decoration: none;
    padding: 8px;
    transition: var(--trans);
}

.link-editar:hover { color: var(--azul); }

/* Formulário */
.p-card-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.obrigatorio { color: var(--vermelho); }

.aviso-contato {
    font-size: 0.75rem;
    color: #92400E;
    background: #FFFBEB;
    border: 1px solid #FDE68A;
    border-radius: var(--raio-sm);
    padding: 10px 14px;
}

.btn-confirmar {
    width: 100%;
    padding: 15px;
    font-size: 0.95rem;
    font-weight: 700;
    justify-content: center;
}

.txt-seguranca {
    text-align: center;
    font-size: 0.72rem;
    color: var(--texto-light);
    margin-top: -6px;
}

/* Central (vazio / sucesso) */
.central-wrapper {
    min-height: 50vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}

.central-card {
    background: #fff;
    border: 1px solid var(--borda);
    border-radius: var(--raio);
    padding: 48px 40px;
    text-align: center;
    box-shadow: var(--sombra-md);
    max-width: 460px;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.central-icone { font-size: 4rem; }

.central-card h2 {
    font-family: 'DM Serif Display', serif;
    font-size: 1.8rem;
    font-weight: 400;
    color: var(--texto);
}

.central-card p {
    font-size: 0.875rem;
    color: var(--texto-mid);
}

/* Sucesso */
.sucesso-check { font-size: 4rem; animation: popIn .4s ease; }

@keyframes popIn {
    from { transform: scale(.5); opacity: 0; }
    to   { transform: scale(1);  opacity: 1; }
}

.sucesso-num {
    font-size: 1rem !important;
    font-weight: 700 !important;
    color: var(--verde) !important;
    background: var(--verde-light);
    padding: 6px 20px;
    border-radius: var(--raio-pill);
}

.sucesso-sub {
    font-size: 0.82rem !important;
    color: var(--texto-light) !important;
}

.sucesso-acoes {
    display: flex;
    gap: 12px;
    margin-top: 12px;
    flex-wrap: wrap;
    justify-content: center;
}

/* Comprovante para impressão */
.comprovante-print { display: none; }

@media print {
    body > *:not(main) { display: none !important; }
    main .container > *:not(#tela-sucesso) { display: none !important; }
    #tela-sucesso { display: block !important; }
    .central-wrapper { display: none !important; }
    .comprovante-print { display: block !important; }

    .comprovante-header { text-align: center; margin-bottom: 24px; }
    .comprovante-header h2 { font-size: 1.4rem; }
    .comprovante-header h3 { font-size: 1rem; color: #555; }
}

@media (max-width: 900px) {
    .pedido-layout  { grid-template-columns: 1fr; }
    .pedido-steps   { display: none; }
}
</style>

<script src="../assets/js/main.js"></script>
<script>
function finalizarPedido() {
    const nome      = document.getElementById('cliente_nome').value.trim();
    const telefone  = document.getElementById('cliente_telefone').value.trim();
    const email     = document.getElementById('cliente_email').value.trim();
    const obs       = document.getElementById('observacao').value.trim();

    if (!nome) {
        mostrarNotificacao('❌ Informe seu nome completo.', 'erro');
        document.getElementById('cliente_nome').focus();
        return;
    }

    const btn = document.getElementById('btn-confirmar');
    btn.disabled = true;
    btn.textContent = '⏳ Enviando...';

    fetch(BASE_URL + '/api/pedidos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            acao:             'gerar',
            empresa_id:       1,
            cliente_nome:     nome,
            cliente_telefone: telefone,
            cliente_email:    email,
            observacao:       obs
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Atualizar steps
            document.getElementById('step-dados').classList.remove('ativo');
            document.getElementById('step-dados').classList.add('concluido');
            document.getElementById('step-confirmado').classList.add('done');

            // Mostrar tela de sucesso
            document.getElementById('pedido-layout').style.display = 'none';
            document.getElementById('tela-sucesso').style.display  = 'block';
            document.getElementById('txt-pedido-num').textContent  = `Pedido #${data.pedido_id}`;

            // Gerar comprovante para impressão
            carregarComprovante(data.pedido_id, nome, telefone, email);

        } else {
            mostrarNotificacao(`❌ ${data.message}`, 'erro');
            btn.disabled    = false;
            btn.innerHTML   = '✅ Confirmar Pedido';
        }
    })
    .catch(() => {
        mostrarNotificacao('❌ Erro ao conectar com o servidor.', 'erro');
        btn.disabled  = false;
        btn.innerHTML = '✅ Confirmar Pedido';
    });
}

function carregarComprovante(pedidoId, nome, telefone, email) {
    fetch(BASE_URL + `/api/pedidos.php?id=${pedidoId}`)
    .then(res => res.json())
    .then(data => {
        if (!data.success) return;

        const itens = data.itens;
        const total = data.total;

        const linhas = itens.map(item => `
            <tr>
                <td style="padding:8px;border-bottom:1px solid #eee">${item.nome}</td>
                <td style="padding:8px;border-bottom:1px solid #eee;text-align:center">${item.quantidade}</td>
                <td style="padding:8px;border-bottom:1px solid #eee">R$ ${parseFloat(item.preco).toFixed(2).replace('.', ',')}</td>
                <td style="padding:8px;border-bottom:1px solid #eee;font-weight:700">R$ ${parseFloat(item.subtotal).toFixed(2).replace('.', ',')}</td>
            </tr>
        `).join('');

        document.getElementById('comprovante-body').innerHTML = `
            <p><strong>Pedido:</strong> #${pedidoId}</p>
            <p><strong>Data:</strong> ${new Date().toLocaleString('pt-BR')}</p>
            <p><strong>Cliente:</strong> ${nome}</p>
            ${telefone ? `<p><strong>Telefone:</strong> ${telefone}</p>` : ''}
            ${email    ? `<p><strong>E-mail:</strong> ${email}</p>`    : ''}
            <br>
            <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                <thead>
                    <tr style="background:#0F172A;color:#fff;">
                        <th style="padding:10px;text-align:left">Produto</th>
                        <th style="padding:10px;text-align:center">Qtd</th>
                        <th style="padding:10px;text-align:left">Preço</th>
                        <th style="padding:10px;text-align:left">Subtotal</th>
                    </tr>
                </thead>
                <tbody>${linhas}</tbody>
                <tfoot>
                    <tr style="background:#F8FAFC;font-weight:700;font-size:1rem;">
                        <td colspan="3" style="padding:10px">Total</td>
                        <td style="padding:10px;color:#16A34A">
                            R$ ${parseFloat(total).toFixed(2).replace('.', ',')}
                        </td>
                    </tr>
                </tfoot>
            </table>
        `;
    });
}
</script>

</body>
</html>