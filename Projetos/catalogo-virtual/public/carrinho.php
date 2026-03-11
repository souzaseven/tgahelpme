<?php
// ============================================
// PUBLIC - carrinho.php
// ============================================

require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo = 'Meu Carrinho';
$sessao = $_SESSION['sessao_carrinho'] ?? '';
$itens  = [];
$total  = 0;

if (!empty($sessao)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM carrinho WHERE sessao = ?");
        $stmt->execute([$sessao]);
        $carrinho = $stmt->fetch();

        if ($carrinho) {
            $stmt = $pdo->prepare("
                SELECT ci.id, ci.quantidade, ci.preco_unitario,
                       p.id AS produto_id, p.nome, p.codigo, p.foto,
                       (ci.quantidade * ci.preco_unitario) AS subtotal
                FROM carrinho_itens ci
                INNER JOIN produtos p ON p.id = ci.produto_id
                WHERE ci.carrinho_id = ?
            ");
            $stmt->execute([$carrinho['id']]);
            $itens = $stmt->fetchAll();
            $total = array_sum(array_column($itens, 'subtotal'));
        }

    } catch (PDOException $e) {
        $itens = [];
        $total = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Carrinho — Catálogo Virtual</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- HEADER -->
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

<main class="pagina-carrinho">
    <div class="container">

        <!-- Topo -->
        <div class="carrinho-topo">
            <div>
                <a href="index.php" class="btn-voltar">← Continuar comprando</a>
                <h2>Meu Carrinho</h2>
            </div>
            <?php if (!empty($itens)): ?>
                <button class="btn btn-secondary btn-limpar" onclick="limparCarrinho()">
                    🗑️ Limpar tudo
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($itens)): ?>

            <!-- Estado vazio -->
            <div class="carrinho-vazio">
                <div class="carrinho-vazio-icone">🛒</div>
                <h3>Seu carrinho está vazio</h3>
                <p>Adicione produtos do catálogo para começar seu pedido.</p>
                <a href="index.php" class="btn btn-primary">Ver Catálogo</a>
            </div>

        <?php else: ?>

            <div class="carrinho-layout">

                <!-- COLUNA ITENS -->
                <div class="carrinho-itens">

                    <?php foreach ($itens as $item): ?>
                        <div class="item-card" id="item-<?= (int)$item['id'] ?>">

                            <!-- Foto -->
                            <div class="item-foto">
                                <?php if (!empty($item['foto'])): ?>
                                    <img
                                        src="<?= htmlspecialchars($item['foto']) ?>"
                                        alt="<?= htmlspecialchars($item['nome']) ?>"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
                                    >
                                    <div class="item-foto-placeholder" style="display:none">📦</div>
                                <?php else: ?>
                                    <div class="item-foto-placeholder">📦</div>
                                <?php endif; ?>
                            </div>

                            <!-- Info -->
                            <div class="item-info">
                                <span class="item-nome">
                                    <?= htmlspecialchars($item['nome']) ?>
                                </span>
                                <?php if (!empty($item['codigo'])): ?>
                                    <span class="item-codigo">
                                        COD: <?= htmlspecialchars($item['codigo']) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="item-preco-unit">
                                    R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?> / un.
                                </span>
                            </div>

                            <!-- Controle quantidade -->
                            <div class="item-quantidade">
                                <button
                                    class="btn-qtd"
                                    onclick="atualizarQuantidade(<?= (int)$item['id'] ?>, <?= (int)$item['quantidade'] - 1 ?>)"
                                    <?= $item['quantidade'] <= 1 ? 'disabled' : '' ?>
                                >−</button>
                                <span class="qtd-valor"><?= (int)$item['quantidade'] ?></span>
                                <button
                                    class="btn-qtd"
                                    onclick="atualizarQuantidade(<?= (int)$item['id'] ?>, <?= (int)$item['quantidade'] + 1 ?>)"
                                >+</button>
                            </div>

                            <!-- Subtotal -->
                            <div class="item-subtotal">
                                <span class="subtotal-label">Subtotal</span>
                                <strong class="subtotal-valor">
                                    R$ <?= number_format($item['subtotal'], 2, ',', '.') ?>
                                </strong>
                            </div>

                            <!-- Remover -->
                            <button
                                class="item-remover"
                                onclick="removerDoCarrinho(<?= (int)$item['id'] ?>)"
                                title="Remover item"
                            >✕</button>

                        </div>
                    <?php endforeach; ?>

                </div>

                <!-- COLUNA RESUMO -->
                <div class="carrinho-resumo">

                    <div class="resumo-card">
                        <h3>Resumo do Pedido</h3>

                        <div class="resumo-linhas">
                            <div class="resumo-linha">
                                <span>Subtotal (<?= count($itens) ?> item<?= count($itens) > 1 ? 's' : '' ?>)</span>
                                <span>R$ <?= number_format($total, 2, ',', '.') ?></span>
                            </div>
                            <div class="resumo-linha">
                                <span>Frete</span>
                                <span class="frete-gratis">A combinar</span>
                            </div>
                        </div>

                        <div class="resumo-total">
                            <span>Total</span>
                            <strong>R$ <?= number_format($total, 2, ',', '.') ?></strong>
                        </div>

                        <a href="pedido.php" class="btn btn-success btn-finalizar">
                            ✅ Finalizar Pedido
                        </a>

                        <a href="index.php" class="btn-continuar">
                            ← Continuar comprando
                        </a>
                    </div>

                    <!-- Segurança -->
                    <div class="resumo-seguranca">
                        <span>🔒 Pedido seguro</span>
                        <span>📋 Gera comprovante</span>
                        <span>✅ Sem cadastro</span>
                    </div>

                </div>

            </div>

        <?php endif; ?>

    </div>
</main>

<!-- FOOTER -->
<footer class="footer-principal">
    <div class="container">
        <div class="footer-inner">
            <span class="footer-logo">📦 Catálogo Virtual</span>
            <p>&copy; <?= date('Y') ?> — Todos os direitos reservados</p>
        </div>
    </div>
</footer>

<style>
/* Topo do carrinho */
.carrinho-topo {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 28px;
    gap: 16px;
    flex-wrap: wrap;
}

.carrinho-topo h2 {
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

.btn-limpar {
    font-size: 0.8rem;
    padding: 8px 14px;
}

/* Vazio */
.carrinho-vazio {
    text-align: center;
    padding: 80px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
}

.carrinho-vazio-icone {
    font-size: 4rem;
    opacity: .3;
}

.carrinho-vazio h3 {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--texto);
}

.carrinho-vazio p {
    font-size: 0.9rem;
    color: var(--texto-light);
    max-width: 280px;
}

/* Layout 2 colunas */
.carrinho-layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 24px;
    align-items: start;
}

/* Cards de item */
.carrinho-itens {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.item-card {
    background: #fff;
    border: 1px solid var(--borda);
    border-radius: var(--raio);
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: var(--sombra-sm);
    transition: var(--trans);
}

.item-card:hover {
    border-color: rgba(37,99,235,.2);
    box-shadow: var(--sombra-md);
}

/* Foto do item */
.item-foto {
    width: 64px;
    height: 64px;
    flex-shrink: 0;
    border-radius: var(--raio-sm);
    overflow: hidden;
    background: #F1F5F9;
    border: 1px solid var(--borda);
}

.item-foto img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 6px;
}

.item-foto-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
}

/* Info do item */
.item-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.item-nome {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--texto);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.item-codigo {
    font-size: 0.72rem;
    color: var(--texto-light);
    font-family: monospace;
    background: #F1F5F9;
    padding: 1px 6px;
    border-radius: 4px;
    width: fit-content;
}

.item-preco-unit {
    font-size: 0.78rem;
    color: var(--texto-mid);
}

/* Controle de quantidade */
.item-quantidade {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #F8FAFC;
    border: 1px solid var(--borda);
    border-radius: var(--raio-pill);
    padding: 4px 8px;
    flex-shrink: 0;
}

.btn-qtd {
    width: 26px;
    height: 26px;
    border: none;
    background: #fff;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 700;
    color: var(--texto-mid);
    transition: var(--trans);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--sombra-sm);
}

.btn-qtd:hover:not(:disabled) {
    background: var(--azul);
    color: #fff;
}

.btn-qtd:disabled {
    opacity: .3;
    cursor: not-allowed;
    box-shadow: none;
}

.qtd-valor {
    font-weight: 700;
    font-size: 0.9rem;
    min-width: 20px;
    text-align: center;
    color: var(--texto);
}

/* Subtotal */
.item-subtotal {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    flex-shrink: 0;
    min-width: 90px;
}

.subtotal-label {
    font-size: 0.7rem;
    color: var(--texto-light);
    text-transform: uppercase;
    letter-spacing: .5px;
}

.subtotal-valor {
    font-size: 1rem;
    font-weight: 700;
    color: var(--texto);
}

/* Botão remover */
.item-remover {
    background: none;
    border: none;
    color: var(--texto-light);
    font-size: 0.85rem;
    cursor: pointer;
    padding: 6px;
    border-radius: 50%;
    transition: var(--trans);
    flex-shrink: 0;
    line-height: 1;
}

.item-remover:hover {
    background: var(--vermelho-light);
    color: var(--vermelho);
}

/* Card de resumo */
.resumo-card {
    background: #fff;
    border: 1px solid var(--borda);
    border-radius: var(--raio);
    padding: 24px;
    box-shadow: var(--sombra-sm);
    position: sticky;
    top: 88px;
}

.resumo-card h3 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--texto);
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--borda);
}

.resumo-linhas {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
}

.resumo-linha {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
    color: var(--texto-mid);
}

.frete-gratis {
    font-size: 0.78rem;
    background: var(--amarelo-light);
    color: var(--amarelo);
    padding: 2px 8px;
    border-radius: var(--raio-pill);
    font-weight: 600;
}

.resumo-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    border-top: 2px solid var(--borda);
    margin-bottom: 20px;
}

.resumo-total span {
    font-size: 1rem;
    font-weight: 700;
    color: var(--texto);
}

.resumo-total strong {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--verde);
    letter-spacing: -.5px;
}

.btn-finalizar {
    width: 100%;
    padding: 13px;
    font-size: 0.95rem;
    justify-content: center;
    margin-bottom: 12px;
}

.btn-continuar {
    display: block;
    text-align: center;
    font-size: 0.8rem;
    color: var(--texto-light);
    text-decoration: none;
    transition: var(--trans);
    padding: 6px;
}

.btn-continuar:hover { color: var(--azul); }

/* Segurança */
.resumo-seguranca {
    display: flex;
    justify-content: space-between;
    margin-top: 14px;
    padding: 12px;
    background: #F8FAFC;
    border-radius: var(--raio-sm);
    border: 1px solid var(--borda);
}

.resumo-seguranca span {
    font-size: 0.7rem;
    color: var(--texto-light);
    font-weight: 600;
}

@media (max-width: 900px) {
    .carrinho-layout { grid-template-columns: 1fr; }
    .resumo-card { position: static; }
}

@media (max-width: 600px) {
    .item-card { flex-wrap: wrap; }
    .item-subtotal { min-width: auto; }
    .resumo-seguranca { flex-direction: column; gap: 6px; align-items: center; }
}
</style>

<script src="../assets/js/main.js"></script>
<script>
function limparCarrinho() {
    if (!confirm('Deseja limpar todo o carrinho?')) return;

    fetch(BASE_URL + '/api/carrinho.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'limpar' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
    });
}
</script>

</body>
</html>