<?php
// ============================================
// ADMIN - index.php (Dashboard)
// ============================================

require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo = 'Painel Administrativo';

try {
    $total_produtos    = $pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1")->fetchColumn();
    $total_pedidos     = $pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
    $total_empresas    = $pdo->query("SELECT COUNT(*) FROM empresas WHERE status = 'ativo'")->fetchColumn();
    $pedidos_pendentes = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status = 'pendente'")->fetchColumn();

    $ultimos_pedidos = $pdo->query("
        SELECT p.id, p.cliente_nome, p.data, p.status,
               SUM(pi.quantidade * pi.preco) AS total
        FROM pedidos p
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        GROUP BY p.id
        ORDER BY p.data DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    $total_produtos    = 0;
    $total_pedidos     = 0;
    $total_empresas    = 0;
    $pedidos_pendentes = 0;
    $ultimos_pedidos   = [];
}

require_once '../includes/header.php';
?>

<main class="pagina-admin">
    <div class="container">

        <!-- Topo -->
        <div class="admin-topo">
            <h2>Painel Administrativo</h2>
            <div class="admin-acoes">
                <a href="produtos/index.php" class="btn btn-primary">+ Gerenciar Produtos</a>
                <a href="pedidos/index.php"  class="btn btn-secondary">📋 Ver Pedidos</a>
            </div>
        </div>

        <!-- Cards de totais -->
        <div class="grid-cards-admin">
            <div class="card-admin azul">
                <div class="card-admin-icone">📦</div>
                <div class="card-admin-info">
                    <span class="card-admin-valor"><?= (int)$total_produtos ?></span>
                    <span class="card-admin-label">Produtos Ativos</span>
                </div>
            </div>
            <div class="card-admin verde">
                <div class="card-admin-icone">📋</div>
                <div class="card-admin-info">
                    <span class="card-admin-valor"><?= (int)$total_pedidos ?></span>
                    <span class="card-admin-label">Total de Pedidos</span>
                </div>
            </div>
            <div class="card-admin laranja">
                <div class="card-admin-icone">⏳</div>
                <div class="card-admin-info">
                    <span class="card-admin-valor"><?= (int)$pedidos_pendentes ?></span>
                    <span class="card-admin-label">Pedidos Pendentes</span>
                </div>
            </div>
            <div class="card-admin roxo">
                <div class="card-admin-icone">🏢</div>
                <div class="card-admin-info">
                    <span class="card-admin-valor"><?= (int)$total_empresas ?></span>
                    <span class="card-admin-label">Empresas Ativas</span>
                </div>
            </div>
        </div>

        <!-- Últimos pedidos -->
        <div class="secao-admin">
            <h3>🕐 Últimos Pedidos</h3>

            <?php if (empty($ultimos_pedidos)): ?>
                <div class="alerta alerta-info">Nenhum pedido registrado ainda.</div>
            <?php else: ?>
                <table class="tabela-admin">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimos_pedidos as $pedido): ?>
                            <tr>
                                <td><strong>#<?= (int)$pedido['id'] ?></strong></td>
                                <td><?= htmlspecialchars($pedido['cliente_nome']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($pedido['data'])) ?></td>
                                <td><strong>R$ <?= number_format($pedido['total'] ?? 0, 2, ',', '.') ?></strong></td>
                                <td>
                                    <span class="badge badge-<?= $pedido['status'] ?>">
                                        <?= ucfirst($pedido['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="pedidos/detalhe.php?id=<?= (int)$pedido['id'] ?>"
                                       class="btn btn-primary btn-sm">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="margin-top:16px;">
                    <a href="pedidos/index.php" class="btn btn-secondary">Ver todos os pedidos →</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Atalhos rápidos -->
        <div class="secao-admin">
            <h3>⚡ Atalhos Rápidos</h3>
            <div class="grid-atalhos">
                <a href="produtos/index.php" class="atalho-card">
                    <span>📦</span>
                    <strong>Produtos</strong>
                    <small>Cadastrar e editar</small>
                </a>
                <a href="produtos/novo.php" class="atalho-card">
                    <span>➕</span>
                    <strong>Novo Produto</strong>
                    <small>Adicionar ao catálogo</small>
                </a>
                <a href="pedidos/index.php" class="atalho-card">
                    <span>📋</span>
                    <strong>Pedidos</strong>
                    <small>Gerenciar pedidos</small>
                </a>
                <a href="../public/index.php" class="atalho-card" target="_blank">
                    <span>🌐</span>
                    <strong>Ver Catálogo</strong>
                    <small>Visualizar como cliente</small>
                </a>
            </div>
        </div>

    </div>
</main>

<script src="../assets/js/main.js"></script>

<?php require_once '../includes/footer.php'; ?>