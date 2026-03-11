<?php
// ============================================
// ADMIN - produtos/index.php (Listar Produtos)
// ============================================

require_once '../../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo     = 'Gerenciar Produtos';
$empresa_id = 1;
$busca      = $_GET['busca'] ?? '';
$mensagem   = $_SESSION['mensagem'] ?? null;
unset($_SESSION['mensagem']);

try {
    $sql = "
        SELECT p.*, e.quantidade AS estoque
        FROM produtos p
        LEFT JOIN estoque e ON e.produto_id = p.id
        WHERE p.empresa_id = ?
    ";
    $params = [$empresa_id];

    if (!empty($busca)) {
        $sql .= " AND (p.nome LIKE ? OR p.codigo LIKE ?)";
        $termo    = "%$busca%";
        $params[] = $termo;
        $params[] = $termo;
    }

    $sql .= " ORDER BY p.nome ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll();

} catch (PDOException $e) {
    $produtos = [];
}

require_once '../../includes/header.php';
?>

<main class="pagina-admin">
    <div class="container">

        <!-- Topo -->
        <div class="admin-topo">
            <h2>Gerenciar Produtos</h2>
            <div class="admin-acoes">
                <a href="../index.php" class="btn btn-secondary">← Dashboard</a>
                <a href="novo.php"     class="btn btn-primary">+ Novo Produto</a>
            </div>
        </div>

        <!-- Mensagem -->
        <?php if ($mensagem): ?>
            <div class="alerta alerta-<?= $mensagem['tipo'] ?>">
                <?= htmlspecialchars($mensagem['texto']) ?>
            </div>
        <?php endif; ?>

        <!-- Busca -->
        <form class="form-busca-admin" method="GET" action="">
            <div class="busca-inner">
                <span class="busca-icone">🔍</span>
                <input
                    type="text"
                    name="busca"
                    placeholder="Buscar por nome ou código..."
                    value="<?= htmlspecialchars($busca) ?>"
                >
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
            <?php if (!empty($busca)): ?>
                <a href="index.php" class="btn btn-secondary">Limpar</a>
            <?php endif; ?>
        </form>

        <!-- Tabela -->
        <?php if (empty($produtos)): ?>
            <div class="estado-vazio">
                <div class="vazio-icone">📦</div>
                <h3>Nenhum produto cadastrado</h3>
                <p>Comece adicionando seu primeiro produto ao catálogo.</p>
                <a href="novo.php" class="btn btn-primary">+ Cadastrar Produto</a>
            </div>
        <?php else: ?>

            <table class="tabela-admin">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Preço</th>
                        <th>Estoque</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $produto): ?>
                        <tr>

                            <!-- Foto -->
                            <td>
                                <?php if (!empty($produto['foto'])): ?>
                                    <img
                                        src="<?= htmlspecialchars($produto['foto']) ?>"
                                        alt="foto"
                                        class="foto-tabela"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block'"
                                    >
                                    <span class="foto-tabela-placeholder" style="display:none">📦</span>
                                <?php else: ?>
                                    <span class="foto-tabela-placeholder">📦</span>
                                <?php endif; ?>
                            </td>

                            <!-- Código -->
                            <td>
                                <span class="cod-tag">
                                    <?= htmlspecialchars($produto['codigo'] ?? '—') ?>
                                </span>
                            </td>

                            <!-- Nome -->
                            <td>
                                <strong class="nome-tabela">
                                    <?= htmlspecialchars($produto['nome']) ?>
                                </strong>
                                <?php if (!empty($produto['descricao'])): ?>
                                    <br>
                                    <small class="desc-tabela">
                                        <?= htmlspecialchars(substr($produto['descricao'], 0, 60)) ?>
                                        <?= strlen($produto['descricao']) > 60 ? '…' : '' ?>
                                    </small>
                                <?php endif; ?>
                            </td>

                            <!-- Preço -->
                            <td>
                                <strong class="preco-tabela">
                                    R$ <?= number_format($produto['preco'], 2, ',', '.') ?>
                                </strong>
                            </td>

                            <!-- Estoque -->
                            <td>
                                <?php if ($produto['estoque'] > 0): ?>
                                    <span class="badge badge-confirmado">
                                        <?= (int)$produto['estoque'] ?> un.
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-cancelado">Sem estoque</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td>
                                <?php if ($produto['ativo']): ?>
                                    <span class="badge badge-confirmado">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-cancelado">Inativo</span>
                                <?php endif; ?>
                            </td>

                            <!-- Ações -->
                            <td>
                                <div class="acoes-tabela">
                                    <a href="editar.php?id=<?= (int)$produto['id'] ?>"
                                       class="btn btn-primary btn-sm">
                                        ✏️ Editar
                                    </a>
                                    <button
                                        class="btn btn-danger btn-sm"
                                        onclick="confirmarExclusao(<?= (int)$produto['id'] ?>, '<?= addslashes($produto['nome']) ?>')"
                                    >
                                        🗑️ Excluir
                                    </button>
                                </div>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="total-registros">
                Total: <strong><?= count($produtos) ?></strong> produto(s)
            </p>

        <?php endif; ?>

    </div>
</main>

<style>
/* Busca */
.form-busca-admin {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    align-items: center;
}

.busca-inner {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.busca-icone {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.9rem;
    pointer-events: none;
}

.busca-inner input {
    width: 100%;
    padding: 10px 14px 10px 36px;
    border: 1.5px solid var(--borda);
    border-radius: var(--raio-sm);
    font-size: 0.875rem;
    font-family: 'DM Sans', sans-serif;
    outline: none;
    transition: var(--trans);
    background: #fff;
    color: var(--texto);
}

.busca-inner input:focus {
    border-color: var(--azul);
    box-shadow: 0 0 0 3px rgba(37,99,235,.12);
}

/* Foto na tabela */
.foto-tabela {
    width: 52px;
    height: 52px;
    object-fit: cover;
    border-radius: var(--raio-sm);
    border: 1px solid var(--borda);
}

.foto-tabela-placeholder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    background: #F1F5F9;
    border-radius: var(--raio-sm);
    border: 1px solid var(--borda);
    font-size: 1.4rem;
}

/* Textos na tabela */
.cod-tag {
    font-family: monospace;
    font-size: 0.78rem;
    background: #F1F5F9;
    color: var(--texto-mid);
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid var(--borda);
}

.nome-tabela {
    font-size: 0.9rem;
    color: var(--texto);
}

.desc-tabela {
    color: var(--texto-light);
    font-size: 0.78rem;
    line-height: 1.4;
}

.preco-tabela {
    color: var(--verde);
    font-size: 0.95rem;
}

.total-registros {
    margin-top: 16px;
    font-size: 0.82rem;
    color: var(--texto-light);
}
</style>

<script src="../../assets/js/main.js"></script>
<script>
function confirmarExclusao(id, nome) {
    if (!confirm(`Deseja excluir o produto "${nome}"?\nEsta ação não pode ser desfeita.`)) return;

    fetch(BASE_URL + '/api/produtos.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacao('✅ Produto excluído com sucesso!', 'sucesso');
            setTimeout(() => location.reload(), 1000);
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