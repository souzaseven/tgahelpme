<?php
// ============================================
// ADMIN - produtos/novo.php (Cadastrar Produto)
// ============================================

require_once '../../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo     = 'Novo Produto';
$empresa_id = 1;
$erros      = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo     = trim($_POST['codigo']     ?? '');
    $nome       = trim($_POST['nome']       ?? '');
    $descricao  = trim($_POST['descricao']  ?? '');
    $preco      = $_POST['preco']           ?? '';
    $quantidade = $_POST['quantidade']      ?? 0;
    $ativo      = isset($_POST['ativo'])    ? 1 : 0;
    $foto_url   = trim($_POST['foto_url']   ?? '');

    if (empty($nome))                          $erros[] = 'O nome do produto é obrigatório.';
    if (!is_numeric($preco) || $preco < 0)     $erros[] = 'Informe um preço válido.';
    if (!is_numeric($quantidade) || $quantidade < 0) $erros[] = 'Informe uma quantidade válida.';

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO produtos (empresa_id, codigo, nome, descricao, preco, foto, ativo)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$empresa_id, $codigo, $nome, $descricao, $preco, $foto_url, $ativo]);
            $produto_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO estoque (produto_id, quantidade) VALUES (?, ?)");
            $stmt->execute([$produto_id, (int)$quantidade]);

            $pdo->commit();

            $_SESSION['mensagem'] = ['tipo' => 'sucesso', 'texto' => "Produto \"{$nome}\" cadastrado com sucesso!"];
            header('Location: index.php');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erros[] = 'Erro ao salvar produto. Tente novamente.';
        }
    }
}

require_once '../../includes/header.php';
?>

<main class="pagina-admin">
    <div class="container">

        <!-- Topo -->
        <div class="admin-topo">
            <div class="topo-titulo">
                <a href="index.php" class="btn-voltar">← Produtos</a>
                <h2>Novo Produto</h2>
            </div>
            <div class="admin-acoes">
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
                <button form="form-produto" type="submit" class="btn btn-success">✅ Salvar Produto</button>
            </div>
        </div>

        <!-- Erros -->
        <?php if (!empty($erros)): ?>
            <div class="alerta alerta-erro">
                <div>
                    <strong>Corrija os erros abaixo:</strong>
                    <ul style="margin-top:6px;padding-left:18px;">
                        <?php foreach ($erros as $erro): ?>
                            <li><?= htmlspecialchars($erro) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Layout em 2 colunas -->
        <form id="form-produto" method="POST" action="">
            <div class="form-layout">

                <!-- COLUNA PRINCIPAL -->
                <div class="form-main">

                    <!-- Card: Informações básicas -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <span class="form-card-icon">📝</span>
                            <h3>Informações Básicas</h3>
                        </div>
                        <div class="form-card-body">

                            <div class="form-grupo">
                                <label for="nome">Nome do Produto <span class="obrigatorio">*</span></label>
                                <input
                                    type="text"
                                    id="nome"
                                    name="nome"
                                    placeholder="Ex: Camiseta Básica Azul"
                                    value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"
                                    required
                                >
                            </div>

                            <div class="form-grupo">
                                <label for="codigo">Código do Produto</label>
                                <input
                                    type="text"
                                    id="codigo"
                                    name="codigo"
                                    placeholder="Ex: CAM-001"
                                    value="<?= htmlspecialchars($_POST['codigo'] ?? '') ?>"
                                >
                                <small class="campo-dica">Use um código único para identificar o produto</small>
                            </div>

                            <div class="form-grupo">
                                <label for="descricao">Descrição</label>
                                <textarea
                                    id="descricao"
                                    name="descricao"
                                    rows="4"
                                    placeholder="Descreva o produto, materiais, características..."
                                ><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
                            </div>

                        </div>
                    </div>

                    <!-- Card: Preço e Estoque -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <span class="form-card-icon">💰</span>
                            <h3>Preço e Estoque</h3>
                        </div>
                        <div class="form-card-body">
                            <div class="form-linha-dupla">
                                <div class="form-grupo">
                                    <label for="preco">Preço (R$) <span class="obrigatorio">*</span></label>
                                    <div class="input-prefix">
                                        <span class="prefix-text">R$</span>
                                        <input
                                            type="number"
                                            id="preco"
                                            name="preco"
                                            placeholder="0,00"
                                            step="0.01"
                                            min="0"
                                            value="<?= htmlspecialchars($_POST['preco'] ?? '') ?>"
                                            required
                                        >
                                    </div>
                                </div>
                                <div class="form-grupo">
                                    <label for="quantidade">Qtd em Estoque <span class="obrigatorio">*</span></label>
                                    <div class="input-prefix">
                                        <span class="prefix-text">un.</span>
                                        <input
                                            type="number"
                                            id="quantidade"
                                            name="quantidade"
                                            placeholder="0"
                                            min="0"
                                            value="<?= htmlspecialchars($_POST['quantidade'] ?? '0') ?>"
                                            required
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- COLUNA LATERAL -->
                <div class="form-side">

                    <!-- Card: Foto -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <span class="form-card-icon">🖼️</span>
                            <h3>Imagem do Produto</h3>
                        </div>
                        <div class="form-card-body">

                            <!-- Preview -->
                            <div class="foto-preview" id="foto-preview">
                                <div class="foto-preview-empty">
                                    <span>📦</span>
                                    <small>Preview da imagem</small>
                                </div>
                            </div>

                            <div class="form-grupo" style="margin-top:14px;">
                                <label for="foto_url">URL da Imagem</label>
                                <input
                                    type="url"
                                    id="foto_url"
                                    name="foto_url"
                                    placeholder="https://exemplo.com/foto.jpg"
                                    value="<?= htmlspecialchars($_POST['foto_url'] ?? '') ?>"
                                    oninput="previewFoto(this.value)"
                                >
                                <small class="campo-dica">Cole o link direto de uma imagem</small>
                            </div>

                        </div>
                    </div>

                    <!-- Card: Publicação -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <span class="form-card-icon">⚙️</span>
                            <h3>Publicação</h3>
                        </div>
                        <div class="form-card-body">

                            <label class="toggle-wrapper">
                                <input
                                    type="checkbox"
                                    name="ativo"
                                    value="1"
                                    id="ativo"
                                    <?= isset($_POST['ativo']) || !isset($_POST['nome']) ? 'checked' : '' ?>
                                >
                                <div class="toggle-track">
                                    <div class="toggle-thumb"></div>
                                </div>
                                <div class="toggle-info">
                                    <strong>Produto ativo</strong>
                                    <small>Visível no catálogo público</small>
                                </div>
                            </label>

                        </div>
                    </div>

                    <!-- Card: Dicas -->
                    <div class="form-card form-card-dicas">
                        <div class="form-card-header">
                            <span class="form-card-icon">💡</span>
                            <h3>Dicas</h3>
                        </div>
                        <div class="form-card-body">
                            <ul class="lista-dicas">
                                <li>Use códigos únicos por produto</li>
                                <li>Imagens quadradas ficam melhores</li>
                                <li>Produtos inativos ficam ocultos</li>
                                <li>O estoque baixa a cada pedido</li>
                            </ul>
                        </div>
                    </div>

                </div>

            </div>
        </form>

    </div>
</main>

<style>
/* Layout */
.topo-titulo {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.btn-voltar {
    font-size: 0.78rem;
    color: var(--texto-light);
    text-decoration: none;
    transition: var(--trans);
}

.btn-voltar:hover { color: var(--azul); }

.form-layout {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 24px;
    align-items: start;
}

.form-main,
.form-side {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Cards do formulário */
.form-card {
    background: #fff;
    border-radius: var(--raio);
    border: 1px solid var(--borda);
    box-shadow: var(--sombra-sm);
    overflow: hidden;
}

.form-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--borda);
    background: #FAFBFC;
}

.form-card-icon { font-size: 1rem; }

.form-card-header h3 {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--texto);
    letter-spacing: -.1px;
}

.form-card-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Obrigatório */
.obrigatorio { color: var(--vermelho); margin-left: 2px; }

/* Input com prefixo */
.input-prefix {
    position: relative;
    display: flex;
    align-items: center;
}

.prefix-text {
    position: absolute;
    left: 12px;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--texto-light);
    pointer-events: none;
    z-index: 1;
}

.input-prefix input {
    padding-left: 36px !important;
}

/* Preview da foto */
.foto-preview {
    width: 100%;
    height: 200px;
    background: #F8FAFC;
    border-radius: var(--raio-sm);
    border: 2px dashed var(--borda);
    overflow: hidden;
    transition: var(--trans);
    display: flex;
    align-items: center;
    justify-content: center;
}

.foto-preview-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    color: var(--texto-light);
    font-size: 2.5rem;
}

.foto-preview-empty small {
    font-size: 0.78rem;
    font-family: 'DM Sans', sans-serif;
}

.foto-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 12px;
}

/* Toggle switch */
.toggle-wrapper {
    display: flex;
    align-items: center;
    gap: 14px;
    cursor: pointer;
    user-select: none;
}

.toggle-wrapper input { display: none; }

.toggle-track {
    width: 44px;
    height: 24px;
    background: #CBD5E1;
    border-radius: var(--raio-pill);
    position: relative;
    flex-shrink: 0;
    transition: var(--trans);
}

.toggle-thumb {
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    position: absolute;
    top: 3px;
    left: 3px;
    transition: var(--trans);
    box-shadow: 0 1px 3px rgba(0,0,0,.2);
}

.toggle-wrapper input:checked ~ .toggle-track {
    background: var(--verde);
}

.toggle-wrapper input:checked ~ .toggle-track .toggle-thumb {
    transform: translateX(20px);
}

.toggle-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.toggle-info strong {
    font-size: 0.875rem;
    color: var(--texto);
    font-weight: 600;
}

.toggle-info small {
    font-size: 0.75rem;
    color: var(--texto-light);
}

/* Dicas */
.form-card-dicas { border-left: 3px solid var(--azul); }

.lista-dicas {
    padding-left: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.lista-dicas li {
    font-size: 0.82rem;
    color: var(--texto-mid);
    line-height: 1.4;
}

/* Dica de campo */
.campo-dica {
    font-size: 0.75rem;
    color: var(--texto-light);
    margin-top: 2px;
}

@media (max-width: 900px) {
    .form-layout { grid-template-columns: 1fr; }
}
</style>

<script src="../../assets/js/main.js"></script>
<script>
function previewFoto(url) {
    const preview = document.getElementById('foto-preview');

    if (!url) {
        preview.innerHTML = `
            <div class="foto-preview-empty">
                <span>📦</span>
                <small>Preview da imagem</small>
            </div>`;
        return;
    }

    preview.innerHTML = `
        <img
            src="${url}"
            alt="Preview"
            onerror="this.parentElement.innerHTML='<div class=\\'foto-preview-empty\\'><span>❌</span><small>URL inválida</small></div>'"
        >`;
}
</script>

<?php require_once '../../includes/footer.php'; ?>