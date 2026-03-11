<?php
// ============================================
// ADMIN - pedidos/index.php (Listar Pedidos)
// ============================================

require_once '../../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo     = 'Gerenciar Pedidos';
$empresa_id = 1;
$filtro     = $_GET['status'] ?? '';
$mensagem   = $_SESSION['mensagem'] ?? null;
unset($_SESSION['mensagem']);

try {
    $sql = "
        SELECT p.id, p.cliente_nome, p.cliente_telefone, p.cliente_email,
               p.data, p.status,
               COUNT(pi.id) AS total_itens,
               SUM(pi.quantidade * pi.preco) AS total_valor
        FROM pedidos p
        LEFT JOIN pedido_itens pi ON pi.pedido_id = p.id
        WHERE p.empresa_id = ?
    ";

    $params = [$empresa_id];

    if (!empty($filtro)) {
        $sql .= " AND p.status = ?";
        $params[] = $filtro;
    }

    $sql .= " GROUP BY p.id ORDER BY p.data DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();

    $totais = $pdo->prepare("SELECT status, COUNT(*) AS total FROM pedidos WHERE empresa_id = ? GROUP BY status");
    $totais->execute([$empresa_id]);
    $contadores = [];
    foreach ($totais->fetchAll() as $row) {
        $contadores[$row['status']] = $row['total'];
    }

} catch (PDOException $e) {
    $pedidos    = [];
    $contadores = [];
}

require_once '../../includes/header.php';
?>

<main class="pagina-admin">
    <div class="container">

        <!-- Topo -->
        <div class="admin-topo">
            <div class="topo-titulo">
                <a href="../index.php" class="btn-voltar">← Dashboard</a>
                <h2>Gerenciar Pedidos</h2>
            </div>
        </div>

        <!-- Mensagem -->
        <?php if ($mensagem): ?>
            <div class="alerta alerta-<?= $mensagem['tipo'] ?>">
                <?= htmlspecialchars($mensagem['texto']) ?>
            </div>
        <?php endif; ?>

        <!-- Cards de resumo -->
        <div class="pedidos-resumo">
            <a href="index.php" class="resumo-card <?= empty($filtro) ? 'ativo' : '' ?>">
                <span class="resumo-numero"><?= array_sum($contadores) ?></span>
                <span class="resumo-label">Todos</span>
            </a>
            <a href="index.php?status=pendente" class="resumo-card pendente <?= $filtro === 'pendente' ? 'ativo' : '' ?>">
                <span class="resumo-numero"><?= $contadores['pendente'] ?? 0 ?></span>
                <span class="resumo-label">⏳ Pendentes</span>
            </a>
            <a href="index.php?status=confirmado" class="resumo-card confirmado <?= $filtro === 'confirmado' ? 'ativo' : '' ?>">
                <span class="resumo-numero"><?= $contadores['confirmado'] ?? 0 ?></span>
                <span class="resumo-label">✅ Confirmados</span>
            </a>
            <a href="index.php?status=cancelado" class="resumo-card cancelado <?= $filtro === 'cancelado' ? 'ativo' : '' ?>">
                <span class="resumo-numero"><?= $contadores['cancelado'] ?? 0 ?></span>
                <span class="resumo-label">❌ Cancelados</span>
            </a>
        </div>

        <!-- Tabela -->
        <?php if (empty($pedidos)): ?>
            <div class="estado-vazio">
                <div class="vazio-icone">📋</div>
                <h3>Nenhum pedido encontrado</h3>
                <p>
                    <?php if (!empty($filtro)): ?>
                        Não há pedidos com status "<strong><?= ucfirst($filtro) ?></strong>".
                        <a href="index.php" style="color:var(--azul);">Ver todos</a>
                    <?php else: ?>
                        Ainda não há pedidos registrados no sistema.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>

            <div class="tabela-wrapper">
                <table class="tabela-admin">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Contato</th>
                            <th>Data</th>
                            <th>Itens</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                            <tr>

                                <td>
                                    <span class="pedido-id">#<?= (int)$pedido['id'] ?></span>
                                </td>

                                <td>
                                    <strong class="cliente-nome">
                                        <?= htmlspecialchars($pedido['cliente_nome']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <div class="contato-info">
                                        <?php if (!empty($pedido['cliente_telefone'])): ?>
                                            <span>📱 <?= htmlspecialchars($pedido['cliente_telefone']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($pedido['cliente_email'])): ?>
                                            <span>✉️ <?= htmlspecialchars($pedido['cliente_email']) ?></span>
                                        <?php endif; ?>
                                        <?php if (empty($pedido['cliente_telefone']) && empty($pedido['cliente_email'])): ?>
                                            <span class="sem-contato">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="data-info">
                                        <strong><?= date('d/m/Y', strtotime($pedido['data'])) ?></strong>
                                        <small><?= date('H:i', strtotime($pedido['data'])) ?></small>
                                    </div>
                                </td>

                                <td>
                                    <span class="itens-count">
                                        <?= (int)$pedido['total_itens'] ?> item(s)
                                    </span>
                                </td>

                                <td>
                                    <strong class="valor-total">
                                        R$ <?= number_format($pedido['total_valor'] ?? 0, 2, ',', '.') ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="badge badge-<?= $pedido['status'] ?>">
                                        <?= ucfirst($pedido['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="acoes-tabela">
                                        <a href="detalhe.php?id=<?= (int)$pedido['id'] ?>"
                                           class="btn btn-primary btn-sm">
                                            👁️ Ver
                                        </a>
                                        <?php if ($pedido['status'] === 'pendente'): ?>
                                            <button
                                                class="btn btn-success btn-sm"
                                                onclick="alterarStatus(<?= (int)$pedido['id'] ?>, 'confirmado')"
                                                title="Confirmar pedido"
                                            >✅</button>
                                            <button
                                                class="btn btn-danger btn-sm"
                                                onclick="alterarStatus(<?= (int)$pedido['id'] ?>, 'cancelado')"
                                                title="Cancelar pedido"
                                            >❌</button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="total-registros">
                Exibindo <strong><?= count($pedidos) ?></strong> pedido(s)
                <?php if (!empty($filtro)): ?>
                    com status <strong><?= ucfirst($filtro) ?></strong>
                    — <a href="index.php" style="color:var(--azul);text-decoration:none;">Ver todos</a>
                <?php endif; ?>
            </p>

        <?php endif; ?>

    </div>
</main>

<style>
/* Topo */
.topo-titulo { display: flex; flex-direction: column; gap: 4px; }
.btn-voltar  { font-size: 0.78rem; color: var(--texto-light); text-decoration: none; transition: var(--trans); }
.btn-voltar:hover { color: var(--azul); }

/* Cards de resumo */
.pedidos-resumo {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}

.resumo-card {
    background: #fff;
    border: 1.5px solid var(--borda);
    border-radius: var(--raio);
    padding: 20px;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    transition: var(--trans);
    box-shadow: var(--sombra-sm);
}

.resumo-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--sombra-md);
}

.resumo-card.ativo {
    border-color: var(--azul);
    background: var(--azul-light);
}

.resumo-card.pendente.ativo   { border-color: var(--amarelo); background: var(--amarelo-light); }
.resumo-card.confirmado.ativo { border-color: var(--verde);   background: var(--verde-light); }
.resumo-card.cancelado.ativo  { border-color: var(--vermelho);background: var(--vermelho-light); }

.resumo-numero {
    font-size: 2rem;
    font-weight: 700;
    color: var(--texto);
    line-height: 1;
    font-family: 'DM Serif Display', serif;
}

.resumo-card.ativo       .resumo-numero { color: var(--azul); }
.resumo-card.pendente.ativo   .resumo-numero { color: var(--amarelo); }
.resumo-card.confirmado.ativo .resumo-numero { color: var(--verde); }
.resumo-card.cancelado.ativo  .resumo-numero { color: var(--vermelho); }

.resumo-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--texto-mid);
    text-align: center;
}

/* Tabela wrapper */
.tabela-wrapper {
    border-radius: var(--raio);
    overflow: hidden;
    box-shadow: var(--sombra-sm);
}

/* Células específicas */
.pedido-id {
    font-family: monospace;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--texto-mid);
    background: #F1F5F9;
    padding: 3px 8px;
    border-radius: 4px;
}

.cliente-nome {
    font-size: 0.9rem;
    color: var(--texto);
}

.contato-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
    font-size: 0.78rem;
    color: var(--texto-mid);
}

.sem-contato { color: var(--texto-light); }

.data-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.data-info strong { font-size: 0.85rem; color: var(--texto); }
.data-info small  { font-size: 0.75rem; color: var(--texto-light); }

.itens-count {
    font-size: 0.78rem;
    background: #F1F5F9;
    color: var(--texto-mid);
    padding: 3px 8px;
    border-radius: 4px;
    white-space: nowrap;
}

.valor-total {
    color: var(--verde);
    font-size: 0.95rem;
}

.total-registros {
    margin-top: 16px;
    font-size: 0.82rem;
    color: var(--texto-light);
}

@media (max-width: 768px) {
    .pedidos-resumo { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 480px) {
    .pedidos-resumo { grid-template-columns: 1fr 1fr; }
}
</style>

<script src="../../assets/js/main.js"></script>
<script>
function alterarStatus(pedidoId, status) {
    const labels = { confirmado: 'confirmar', cancelado: 'cancelar' };
    if (!confirm(`Deseja ${labels[status]} o pedido #${pedidoId}?`)) return;

    fetch(BASE_URL + '/api/pedidos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            acao:      'atualizar_status',
            pedido_id: pedidoId,
            status:    status
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacao('✅ Status atualizado!', 'sucesso');
            setTimeout(() => location.reload(), 800);
        } else {
            mostrarNotificacao(`❌ ${data.message}`, 'erro');
        }
    })
    .catch(() => {
        mostrarNotificacao('❌ Erro ao conectar com o servidor.', 'erro');
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>