<?php
// ============================================
// ADMIN - produtos/editar.php (Editar Produto)
// ============================================

require_once '../../config/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$titulo = 'Editar Produto';
$erros  = [];
$id     = $_GET['id'] ?? null;

// Validar ID
if (!$id || !is_numeric($id)) {
    header('Location: index.php');
    exit;
}

// Buscar produto existente
try {
    $stmt = $pdo->prepare("
        SELECT p.*, e.quantidade AS estoque
        FROM produtos p
        LEFT JOIN estoque e ON e.produto_id = p.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $produto = $stmt->fetch();

    if (!$produto) {
        $_SESSION['mensagem'] = [
            'tipo'  => 'erro',
            'texto' => 'Produto não encontrado.'
        ];
        header('Location: index.php');
        exit;
    }

} catch (PDOException $e) {
    header('Location: index.php');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo     = trim($_POST['codigo']     ?? '');
    $nome       = trim($_POST['nome']       ?? '');
    $descricao  = trim($_POST['descricao']  ?? '');
    $preco      = $_POST['preco']           ?? '';
    $quantidade = $_POST['quantidade']      ?? 0;
    $ativo      = isset($_POST['ativo'])    ? 1 : 0;
    $foto_url   = trim($_POST['foto_url']   ?? '');

    // Validações
    if (empty($nome)) {
        $erros[] = 'O nome do produto é obrigatório.';
    }

    if (!is_numeric($preco) || $preco < 0) {
        $erros[] = 'Informe um preço válido.';
    }

    if (!is_numeric($quantidade) || $quantidade < 0) {
        $erros[] = 'Informe uma quantidade válida.';
    }

    // Salvar se não houver erros
    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            // Atualizar produto
            $stmt = $pdo->prepare("
                UPDATE produtos SET
                    codigo     = ?,
                    nome       = ?,
                    descricao  = ?,
                    preco      = ?,
                    foto       = ?,
                    ativo      = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $codigo,
                $nome,
                $descricao,
                $preco,
                $foto_url,
                $ativo,
                $id
            ]);

            // Atualizar estoque
            $stmt = $pdo->prepare("
                UPDATE estoque SET quantidade = ? WHERE produto_id = ?
            ");
            $stmt->execute([(int)$quantidade, $id]);

            // Se não existe registro de estoque, inserir
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO estoque (produto_id, quantidade) VALUES (?, ?)
                ");
                $stmt->execute([$id, (int)$quantidade]);
            }

            $pdo->commit();

            $_SESSION['mensagem'] = [
                'tipo'  => 'sucesso',
                'texto' => "Produto \"{$nome}\" atualizado com sucesso!"
            ];

            header('Location: index.php');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $erros[] = 'Erro ao atualizar produto. Tente novamente.';
        }
    }

    // Manter valores digitados em caso de erro
    $produto['codigo']     = $codigo;
    $produto['nome']       = $nome;
    $produto['descricao']  = $descricao;
    $produto['preco']      = $preco;
    $produto['estoque']    = $quantidade;
    $produto['ativo']      = $ativo;
    $produto['foto']       = $foto_url;
}

require_once '../../includes/header.php';
?>

<main class="pagina-admin">
    <div class="container">

        <!-- Topo -->
        <div class="admin-topo">
            <h2>✏️ Editar Produto</h2>
            <div class="admin-acoes">
                <a href="index.php" class="btn btn-secondary">← Voltar</a>
                
                    href="../../public/index.php"
                    class="btn btn-primary"
                    target="_blank"
                >
                    🌐 Ver no Catálogo
                </a>
            </div>
        </div>

        <!-- Erros -->
        <?php if (!empty($erros)): ?>
            <div class="alerta alerta-erro">
                <strong>Corrija os erros abaixo:</strong>
                <ul style="margin-top:8px;padding-left:20px;">
                    <?php foreach ($erros as $erro): ?>
                        <li><?= htmlspecialchars($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Formulário -->
        <div class="form-container">
            <form method="POST" action="">

                <div class="form-grid">

                    <!-- Coluna esquerda -->
                    <div class="form-coluna">

                        <div class="form-grupo">
                            <label for="nome">Nome do Produto *</label>
                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                placeholder="Ex: Camiseta Básica Azul"
                                value="<?= htmlspecialchars($produto['nome']) ?>"
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
                                value="<?= htmlspecialchars($produto['codigo'] ?? '') ?>"
                            >
                        </div>

                        <div class="form-grupo">
                            <label for="descricao">Descrição</label>
                            <textarea
                                id="descricao"
                                name="descricao"
                                rows="4"
                                placeholder="Descreva o produto..."
                            ><?= htmlspecialchars($produto['descricao'] ?? '') ?></textarea>
                        </div>

                        <div class="form-linha-dupla">

                            <div class="form-grupo">
                                <label for="preco">Preço (R$) *</label>
                                <input
                                    type="number"
                                    id="preco"
                                    name="preco"
                                    placeholder="0,00"
                                    step="0.01"
                                    min="0"
                                    value="<?= htmlspecialchars($produto['preco']) ?>"
                                    required
                                >
                            </div>

                            <div class="form-grupo">
                                <label for="quantidade">Qtd em Estoque *</label>
                                <input
                                    type="number"
                                    id="quantidade"
                                    name="quantidade"
                                    placeholder="0"
                                    min="0"
                                    value="<?= (int)($produto['estoque'] ?? 0) ?>"
                                    required
                                >
                            </div>

                        </div>

                    </div>

                    <!-- Coluna direita -->
                    <div class="form-coluna">

                        <div class="form-grupo">
                            <label for="foto_url">URL da Foto</label>
                            <input
                                type="url"
                                id="foto_url"
                                name="foto_url"
                                placeholder="https://exemplo.com/foto.jpg"
                                value="<?= htmlspecialchars($produto['foto'] ?? '') ?>"
                                oninput="previewFoto(this.value)"
                            >
                            <small class="campo-dica">
                                Cole o link direto de uma imagem da internet
                            </small>
                        </div>

                        <!-- Preview da foto -->
                        <div class="foto-preview" id="foto-preview">
                            <?php if (!empty($produto['foto'])): ?>
                                <img
                                    src="<?= htmlspecialchars($produto['foto']) ?>"
                                    alt="Preview"
                                    onerror="this.parentElement.innerHTML='<span>❌</span><small>URL inválida</small>'"
                                >
                            <?php else: ?>
                                <span>📦</span>
                                <small>Preview da imagem</small>
                            <?php endif; ?>
                        </div>

                        <div class="form-grupo">
                            <label class="label-checkbox">
                                <input
                                    type="checkbox"
                                    name="ativo"
                                    value="1"
                                    <?= $produto['ativo'] ? 'checked' : '' ?>
                                >
                                Produto ativo no catálogo
                            </label>
                        </div>

                        <!-- Info do produto -->
                        <div class="card-resumo-produto">
                            <h4>📋 Informações</h4>
                            <ul>
                                <li><strong>ID:</strong> #<?= (int)$produto['id'] ?></li>
                                <li>
                                    <strong>Status atual:</strong>
                                    <?= $produto['ativo'] ? '✅ Ativo' : '❌ Inativo' ?>
                                </li>
                                <li>
                                    <strong>Estoque atual:</strong>
                                    <?= (int)($produto['estoque'] ?? 0) ?> unidades
                                </li>
                            </ul>
                        </div>

                    </div>

                </div>

                <!-- Botões -->
                <div class="form-botoes">
                    <button
                        type="button"
                        class="btn btn-danger"
                        onclick="confirmarExclusao(<?= (int)$id ?>, '<?= addslashes($produto['nome']) ?>')"
                    >
                        🗑️ Excluir Produto
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-success">
                        ✅ Salvar Alterações
                    </button>
                </div>

            </form>
        </div>

    </div>
</main>

<style>
.pagina-admin  { padding: 40px 0; }
.admin-topo    { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 16px; }
.admin-topo h2 { font-size: 1.8rem; color: #2c3e50; }
.admin-acoes   { display: flex; gap: 10px; }

.form-container {
    background: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.form-grid         { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 24px; }
.form-coluna       { display: flex; flex-direction: column; gap: 20px; }
.form-grupo        { display: flex; flex-direction: column; gap: 6px; }
.form-linha-dupla  { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.form-grupo label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #555;
}

.form-grupo input,
.form-grupo textarea {
    padding: 10px 14px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.2s;
    font-family: inherit;
}

.form-grupo input:focus,
.form-grupo textarea:focus {
    border-color: #3498db;
}

.campo-dica { color: #999; font-size: 0.78rem; }

.label-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #555;
    cursor: pointer;
}

.foto-preview {
    width: 100%;
    height: 180px;
    background: #ecf0f1;
    border-radius: 8px;
    border: 2px dashed #bdc3c7;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 2.5rem;
    color: #bdc3c7;
    overflow: hidden;
}

.foto-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 6px;
}

.card-resumo-produto {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 16px;
    border-left: 4px solid #27ae60;
}

.card-resumo-produto h4 {
    font-size: 0.9rem;
    color: #2c3e50;
    margin-bottom: 10px;
}

.card-resumo-produto ul {
    padding-left: 18px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.card-resumo-produto li {
    font-size: 0.82rem;
    color: #666;
}

.form-botoes {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid #ecf0f1;
}

@media (max-width: 768px) {
    .form-grid        { grid-template-columns: 1fr; }
    .form-linha-dupla { grid-template-columns: 1fr; }
    .form-botoes      { flex-direction: column; }
}
</style>

<script src="/assets/js/main.js"></script>
<script>
function previewFoto(url) {
    const preview = document.getElementById('foto-preview');

    if (!url) {
        preview.innerHTML = '<span>📦</span><small>Preview da imagem</small>';
        return;
    }

    preview.innerHTML = `
        <img
            src="${url}"
            alt="Preview"
            onerror="this.parentElement.innerHTML='<span>❌</span><small>URL inválida</small>'"
        >
    `;
}

function confirmarExclusao(id, nome) {
    if (!confirm(`Deseja excluir o produto "${nome}"?\nEsta ação não pode ser desfeita.`)) return;

    fetch('/api/produtos.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacao('✅ Produto excluído!', 'sucesso');
            setTimeout(() => window.location.href = 'index.php', 1000);
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