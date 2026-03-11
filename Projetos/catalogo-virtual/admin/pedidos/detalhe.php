<?php
// ============================================
// ADMIN - pedidos/detalhe.php (Detalhe do Pedido)
// ============================================

require_once '../../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo = 'Detalhe do Pedido';
$id     = $_GET['id'] ?? null;

// Validar ID
if (!$id || !is_numeric($id)) {
    header('Location: index.php');
    exit;
}

// Buscar pedido
try {
    $stmt = $pdo->prepare("
        SELECT p.*, e.nome AS empresa_nome
        FROM pedidos p
        INNER JOIN empresas e ON e.id = p.empresa_id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        $_SESSION['mensagem'] = [
            'tipo'  => 'erro',
            'texto' => 'Pedido não encontrado.'
        ];
        header('Location: index.php');
        exit;
    }

    // Buscar itens do pedido
    $stmt = $pdo->prepare("
        SELECT pi.quantidade, pi.preco,
               (pi.quantidade * pi.preco) AS subtotal,
               pr.nome, pr.codigo, pr.foto
        FROM pedido_itens pi
        INNER JOIN produtos pr ON pr.id = pi.produto_id
        WHERE pi.pedido_id = ?
    ");
    $stmt->execute([$id]);
    $itens = $stmt->fetchAll();

    $total = array_sum(array_column($itens, 'subtotal'));

} catch (PDOException $e) {
    header('Location: index.php');
    exit;
}

require_once '../../includes/header.php';
?>

<main class="pagina-admin">
    <div class="container">

        <!-- Topo -->
        <div class="admin-topo nao-imprimir">
            <h2>📋 Pedido #<?= (int)$pedido['id'] ?></h2>
            <div class="admin-acoes">
                <a href="index.php" class="btn btn-secondary">← Voltar</a>
                <button class="btn btn-primary" onclick="window.print()">
                    🖨️ Imprimir
                </button>
                <?php if ($pedido['status'] === 'pendente'): ?>
                    <button
                        class="btn btn-success"
                        onclick="alterarStatus(<?= (int)$id ?>, 'confirmado')"
                    >
                        ✅ Confirmar
                    </button>
                    <button
                        class="btn btn-danger"
                        onclick="alterarStatus(<?= (int)$id ?>, 'cancelado')"
                    >
                        ❌ Cancelar
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Área de impressão -->
        <div class="area-impressao">

            <!-- Cabeçalho do pedido -->
            <div class="pedido-cabecalho">
                <div class="pedido-cabecalho-esquerda">
                    <h3>📦 <?= htmlspecialchars($pedido['empresa_nome']) ?></h3>
                    <p class="pedido-subtitulo">Pedido de Venda</p>
                </div>
                <div class="pedido-cabecalho-direita">
                    <p><strong>Pedido #<?= (int)$pedido['id'] ?></strong></p>
                    <p><?= date('d/m/Y \à\s H:i', strtotime($pedido['data'])) ?></p>
                    <span class="badge badge-<?= $pedido['status'] ?> badge-grande">
                        <?= ucfirst($pedido['status']) ?>
                    </span>
                </div>
            </div>

            <!-- Dados do cliente -->
            <div class="secao-detalhe">
                <h4>👤 Dados do Cliente</h4>
                <div class="grid-dados">
                    <div class="dado-item">
                        <span class="dado-label">Nome</span>
                        <span class="dado-valor">
                            <?= htmlspecialchars($pedido['cliente_nome']) ?>
                        </span>
                    </div>
                    <?php if (!empty($pedido['cliente_telefone'])): ?>
                        <div class="dado-item">
                            <span class="dado-label">Telefone</span>
                            <span class="dado-valor">
                                <?= htmlspecialchars($pedido['cliente_telefone']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pedido['cliente_email'])): ?>
                        <div class="dado-item">
                            <span class="dado-label">E-mail</span>
                            <span class="dado-valor">
                                <?= htmlspecialchars($pedido['cliente_email']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Itens do pedido -->
            <div class="secao-detalhe">
                <h4>🛒 Itens do Pedido</h4>

                <table class="tabela-detalhe">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Código</th>
                            <th>Produto</th>
                            <th>Preço Unit.</th>
                            <th>Qtd</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $item): ?>
                            <tr>

                                <!-- Foto -->
                                <td>
                                    <?php if (!empty($item['foto'])): ?>
                                        <img
                                            src="<?= htmlspecialchars($item['foto']) ?>"
                                            alt="foto"
                                            class="foto-tabela"
                                        >
                                    <?php else: ?>
                                        <span class="foto-tabela-placeholder">📦</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Código -->
                                <td>
                                    <small><?= htmlspecialchars($item['codigo'] ?? '—') ?></small>
                                </td>

                                <!-- Nome -->
                                <td>
                                    <strong><?= htmlspecialchars($item['nome']) ?></strong>
                                </td>

                                <!-- Preço unitário -->
                                <td>
                                    R$ <?= number_format($item['preco'], 2, ',', '.') ?>
                                </td>

                                <!-- Quantidade -->
                                <td>
                                    <strong><?= (int)$item['quantidade'] ?></strong>
                                </td>

                                <!-- Subtotal -->
                                <td>
                                    <strong class="texto-verde">
                                        R$ <?= number_format($item['subtotal'], 2, ',', '.') ?>
                                    </strong>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="5"><strong>Total do Pedido</strong></td>
                            <td>
                                <strong class="texto-verde total-grande">
                                    R$ <?= number_format($total, 2, ',', '.') ?>
                                </strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Rodapé de impressão -->
            <div class="rodape-impressao">
                <p>Pedido gerado em <?= date('d/m/Y \à\s H:i', strtotime($pedido['data'])) ?></p>
                <p><?= htmlspecialchars($pedido['empresa_nome']) ?> — Catálogo Virtual</p>
            </div>

        </div>

        <!-- Ações pós detalhe -->
        <div class="acoes-detalhe nao-imprimir">
            <?php if ($pedido['status'] === 'pendente'): ?>
                <div class="alerta alerta-info">
                    ⏳ Este pedido está <strong>pendente</strong>.
                    Confirme ou cancele usando os botões acima.
                </div>
            <?php elseif ($pedido['status'] === 'confirmado'): ?>
                <div class="alerta alerta-sucesso">
                    ✅ Este pedido foi <strong>confirmado</strong>.
                </div>
            <?php else: ?>
                <div class="alerta alerta-erro">
                    ❌ Este pedido foi <strong>cancelado</strong>.
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<style>
.pagina-admin  { padding: 40px 0; }
.admin-topo    { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 16px; }
.admin-topo h2 { font-size: 1.8rem; color: #2c3e50; }
.admin-acoes   { display: flex; gap: 10px; flex-wrap: wrap; }

/* Área de impressão */
.area-impressao {
    background: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 24px;
}

/* Cabeçalho do pedido */
.pedido-cabecalho {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 20px;
    border-bottom: 2px solid #ecf0f1;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.pedido-cabecalho-esquerda h3 {
    font-size: 1.3rem;
    color: #2c3e50;
    margin-bottom: 4px;
}

.pedido-subtitulo {
    color: #999;
    font-size: 0.85rem;
}

.pedido-cabecalho-direita {
    text-align: right;
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-end;
}

.pedido-cabecalho-direita p {
    font-size: 0.9rem;
    color: #555;
}

/* Seções */
.secao-detalhe {
    margin-bottom: 28px;
}

.secao-detalhe h4 {
    font-size: 1rem;
    color: #2c3e50;
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid #ecf0f1;
}

/* Grid de dados do cliente */
.grid-dados {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
}

.dado-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.dado-label {
    font-size: 0.75rem;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.dado-valor {
    font-size: 0.95rem;
    color: #2c3e50;
    font-weight: 600;
}

/* Tabela de itens */
.tabela-detalhe {
    width: 100%;
    border-collapse: collapse;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 6px rgba(0,0,0,0.06);
}

.tabela-detalhe th {
    background-color: #2c3e50;
    color: white;
    padding: 12px 16px;
    text-align: left;
    font-size: 0.85rem;
}

.tabela-detalhe td {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
}

.tabela-detalhe tr:last-child td {
    border-bottom: none;
}

.tabela-detalhe .total-row td {
    background: #f8f9fa;
    font-size: 0.95rem;
}

.foto-tabela {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #eee;
}

.foto-tabela-placeholder {
    display: inline-block;
    width: 48px;
    height: 48px;
    background: #ecf0f1;
    border-radius: 6px;
    text-align: center;
    line-height: 48px;
    font-size: 1.3rem;
}

.texto-verde  { color: #27ae60; }
.total-grande { font-size: 1.1rem; }

/* Badges */
.badge             { padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
.badge-grande      { padding: 6px 14px; font-size: 0.85rem; }
.badge-pendente    { background: #fff3cd; color: #856404; }
.badge-confirmado  { background: #d4edda; color: #155724; }
.badge-cancelado   { background: #f8d7da; color: #721c24; }

/* Rodapé de impressão */
.rodape-impressao {
    margin-top: 30px;
    padding-top: 16px;
    border-top: 1px dashed #ddd;
    text-align: center;
    color: #aaa;
    font-size: 0.8rem;
    display: flex;
    justify-content: space-between;
}

.acoes-detalhe { margin-top: 8px; }

/* IMPRESSÃO */
@media print {
    .nao-imprimir,
    .header-principal,
    .footer-principal {
        display: none !important;
    }

    .area-impressao {
        box-shadow: none;
        padding: 0;
    }

    body { background: white; }
}

@media (max-width: 768px) {
    .pedido-cabecalho         { flex-direction: column; }
    .pedido-cabecalho-direita { text-align: left; align-items: flex-start; }
    .tabela-detalhe           { font-size: 0.8rem; }
}
</style>

<script src="/assets/js/main.js"></script>
<script>
function alterarStatus(pedidoId, status) {
    const labels = { confirmado: 'confirmar', cancelado: 'cancelar' };
    if (!confirm(`Deseja ${labels[status]} o pedido #${pedidoId}?`)) return;

    fetch('/api/pedidos.php', {
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