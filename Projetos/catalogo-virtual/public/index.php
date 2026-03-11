<?php
// ============================================
// PUBLIC - index.php (Catálogo de Produtos)
// ============================================

require_once '../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo     = 'Catálogo de Produtos';
$empresa_id = 1;
$busca      = $_GET['busca'] ?? '';

// Buscar produtos
try {
    $sql = "
        SELECT p.*, e.quantidade AS estoque
        FROM produtos p
        LEFT JOIN estoque e ON e.produto_id = p.id
        WHERE p.ativo = 1 AND p.empresa_id = ?
    ";

    $params = [$empresa_id];

    if (!empty($busca)) {
        $sql .= " AND (p.nome LIKE ? OR p.codigo LIKE ? OR p.descricao LIKE ?)";
        $termo    = "%$busca%";
        $params[] = $termo;
        $params[] = $termo;
        $params[] = $termo;
    }

    $sql .= " ORDER BY p.nome ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll();

    // Buscar empresa
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();

    // Totais para hero stats
    $total_produtos  = count($produtos);
    $total_em_estoque = array_filter($produtos, fn($p) => $p['estoque'] > 0);

} catch (PDOException $e) {
    $produtos         = [];
    $empresa          = ['nome' => 'Catálogo Virtual'];
    $total_produtos   = 0;
    $total_em_estoque = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($empresa['nome'] ?? 'Catálogo Virtual') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<!-- ============================================
     HEADER
     ============================================ -->
<header class="header-principal">
    <div class="container">

        <a href="index.php" class="logo">
            <div class="logo-icon">📦</div>
            <h1><?= htmlspecialchars($empresa['nome'] ?? 'Catálogo') ?></h1>
        </a>

        <div class="header-busca">
            <form method="GET" action="">
                <div class="header-busca-inner">
                    <span class="icone-busca">🔍</span>
                    <input
                        type="text"
                        name="busca"
                        id="busca-header"
                        placeholder="Buscar produtos por nome, código..."
                        value="<?= htmlspecialchars($busca) ?>"
                        autocomplete="off"
                    >
                </div>
            </form>
        </div>

        <nav class="header-nav">
            <a href="../admin/index.php" class="nav-link">⚙️ Admin</a>
            <a href="carrinho.php" class="nav-link nav-carrinho">
                🛒 Carrinho
                <span class="badge-contador" id="contador-carrinho">0</span>
            </a>
        </nav>

    </div>
</header>

<!-- ============================================
     HERO BANNER
     ============================================ -->
<section class="hero-banner">
    <div class="container">
        <div class="hero-content">
            <div class="hero-eyebrow">
                ✦ Catálogo Online
            </div>
            <h2><?= htmlspecialchars($empresa['nome'] ?? 'Nossos Produtos') ?></h2>
            <p>Explore nosso catálogo completo e adicione produtos ao seu pedido de forma simples e rápida.</p>

            <div class="hero-stats">
                <div class="hero-stat">
                    <strong><?= $total_produtos ?></strong>
                    <span>Produtos</span>
                </div>
                <div class="hero-stat">
                    <strong><?= count($total_em_estoque) ?></strong>
                    <span>Disponíveis</span>
                </div>
                <div class="hero-stat">
                    <strong><?= $total_produtos - count($total_em_estoque) ?></strong>
                    <span>Sem estoque</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     BARRA DE FILTROS
     ============================================ -->
<div class="barra-filtros">
    <div class="container">
        <div class="filtros-inner">
            <div class="filtros-esquerda">
                <span class="filtro-label">Filtrar:</span>
                <a href="index.php"
                   class="btn btn-sm <?= empty($busca) ? 'btn-primary' : 'btn-secondary' ?>">
                    Todos
                </a>
                <a href="index.php?disponivel=1"
                   class="btn btn-sm btn-secondary">
                    ✅ Disponíveis
                </a>
            </div>
            <div class="filtros-direita">
                <span class="resultado-count">
                    <?php if (!empty($busca)): ?>
                        <strong><?= count($produtos) ?></strong> resultado(s) para
                        "<strong><?= htmlspecialchars($busca) ?></strong>"
                        — <a href="index.php" style="color:var(--azul);text-decoration:none;">Limpar</a>
                    <?php else: ?>
                        <strong><?= count($produtos) ?></strong> produtos encontrados
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     CATÁLOGO
     ============================================ -->
<main class="pagina-catalogo">
    <div class="container">

        <?php if (empty($produtos)): ?>

            <div class="estado-vazio">
                <div class="vazio-icone">📭</div>
                <h3>Nenhum produto encontrado</h3>
                <p>
                    <?php if (!empty($busca)): ?>
                        Não encontramos resultados para "<strong><?= htmlspecialchars($busca) ?></strong>".
                        Tente outro termo.
                    <?php else: ?>
                        O catálogo ainda não possui produtos cadastrados.
                    <?php endif; ?>
                </p>
                <?php if (!empty($busca)): ?>
                    <a href="index.php" class="btn btn-primary">← Ver todos os produtos</a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <div class="grid-produtos" id="grid-produtos">
                <?php foreach ($produtos as $produto): ?>

                    <div class="card-produto">

                        <!-- Imagem -->
                        <div class="card-produto-img">
                            <?php if (!empty($produto['codigo'])): ?>
                                <span class="badge-codigo">
                                    <?= htmlspecialchars($produto['codigo']) ?>
                                </span>
                            <?php endif; ?>

                            <span class="badge-estoque-card <?= $produto['estoque'] > 0 ? 'disponivel' : 'indisponivel' ?>">
                                <?= $produto['estoque'] > 0 ? '● Em estoque' : '● Indisponível' ?>
                            </span>

                            <?php if (!empty($produto['foto'])): ?>
                                <img
                                    src="<?= htmlspecialchars($produto['foto']) ?>"
                                    alt="<?= htmlspecialchars($produto['nome']) ?>"
                                    loading="lazy"
                                    onerror="this.parentElement.innerHTML='<div class=\'sem-foto\'>📦</div>'"
                                >
                            <?php else: ?>
                                <div class="sem-foto">📦</div>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div class="info-produto">
                            <span class="nome-produto">
                                <?= htmlspecialchars($produto['nome']) ?>
                            </span>

                            <?php if (!empty($produto['descricao'])): ?>
                                <span class="descricao-produto">
                                    <?= htmlspecialchars($produto['descricao']) ?>
                                </span>
                            <?php endif; ?>

                            <div class="preco-produto">
                                <span class="cifrao">R$</span><?= number_format($produto['preco'], 2, ',', '.') ?>
                            </div>
                        </div>

                        <!-- Botão -->
                        <div class="card-footer">
                            <button
                                class="btn-adicionar"
                                <?= $produto['estoque'] <= 0 ? 'disabled' : '' ?>
                                onclick="adicionarAoCarrinho(
                                    <?= (int)$produto['id'] ?>,
                                    '<?= addslashes(htmlspecialchars($produto['nome'])) ?>',
                                    <?= (float)$produto['preco'] ?>
                                )"
                            >
                                <?php if ($produto['estoque'] > 0): ?>
                                    🛒 Adicionar ao Carrinho
                                <?php else: ?>
                                    Indisponível
                                <?php endif; ?>
                            </button>
                        </div>

                    </div>

                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>
</main>

<!-- ============================================
     FOOTER
     ============================================ -->
<footer class="footer-principal">
    <div class="container">
        <div class="footer-inner">
            <span class="footer-logo">📦 <?= htmlspecialchars($empresa['nome'] ?? 'Catálogo Virtual') ?></span>
            <p>&copy; <?= date('Y') ?> — Todos os direitos reservados</p>
        </div>
    </div>
</footer>

<script src="../assets/js/main.js"></script>

<style>
/* Botão pequeno extra */
.btn-sm {
    padding: 6px 14px;
    font-size: 0.78rem;
    border-radius: 6px;
}
</style>

</body>
</html>