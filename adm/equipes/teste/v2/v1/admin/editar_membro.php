<?php
// admin/editar_membro.php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'maiara') {
    header('Location: login.php');
    exit;
}
require_once '../conexao.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: membros.php');
    exit;
}

$erro = '';
$sucesso = '';

$stmt = $pdo->prepare("SELECT * FROM renascer_menbros WHERE id = ?");
$stmt->execute([$id]);
$membro = $stmt->fetch();
if (!$membro) {
    header('Location: membros.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $data_conversao = trim($_POST['data_conversao'] ?? '');
    $status = trim($_POST['status'] ?? 'ativo');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($nome_completo && $data_nascimento) {
        $stmt = $pdo->prepare("UPDATE renascer_menbros SET nome_completo=?, data_nascimento=?, telefone=?, endereco=?, data_conversao=?, status=?, observacoes=? WHERE id=?");
        try {
            $stmt->execute([
                $nome_completo,
                $data_nascimento,
                $telefone,
                $endereco,
                $data_conversao ?: null,
                $status,
                $observacoes,
                $id
            ]);
            $sucesso = 'Dados atualizados com sucesso!';
            // Atualiza dados exibidos no formulário sem reler o banco
            $membro = array_merge($membro, [
                'nome_completo' => $nome_completo,
                'data_nascimento' => $data_nascimento,
                'telefone' => $telefone,
                'endereco' => $endereco,
                'data_conversao' => $data_conversao,
                'status' => $status,
                'observacoes' => $observacoes
            ]);
        } catch (PDOException $e) {
            $erro = 'Erro ao atualizar: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        $erro = 'Preencha os campos obrigatórios.';
    }
}

$titulo_pagina = 'Editar Membro - Igreja Renascer';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<main class="dashboard">
    <div class="topbar">
        <h1>Editar Membro</h1>
        <div class="topbar-actions">
            <a href="membros.php" class="btn btn-secondary btn-sm">Voltar</a>
        </div>
    </div>

    <div class="form-box">
        <?php if ($erro): ?>
            <div class="alert alert-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <div class="alert alert-sucesso"><?= htmlspecialchars($sucesso) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="form-group">
                <label for="nome_completo">Nome Completo *</label>
                <input type="text" name="nome_completo" id="nome_completo" class="form-control" required value="<?= htmlspecialchars($membro['nome_completo']) ?>">
            </div>

            <div class="form-group">
                <label for="data_nascimento">Data de Nascimento *</label>
                <input type="date" name="data_nascimento" id="data_nascimento" class="form-control" required value="<?= htmlspecialchars($membro['data_nascimento']) ?>">
            </div>

            <div class="form-group">
                <label for="telefone">Telefone</label>
                <input type="text" name="telefone" id="telefone" class="form-control" value="<?= htmlspecialchars($membro['telefone']) ?>">
            </div>

            <div class="form-group">
                <label for="endereco">Endereço</label>
                <textarea name="endereco" id="endereco" class="form-control" rows="2"><?= htmlspecialchars($membro['endereco']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="data_conversao">Data de Conversão</label>
                <input type="date" name="data_conversao" id="data_conversao" class="form-control" value="<?= htmlspecialchars($membro['data_conversao']) ?>">
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="ativo" <?= $membro['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inativo" <?= $membro['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>

            <div class="form-group">
                <label for="observacoes">Observações</label>
                <textarea name="observacoes" id="observacoes" class="form-control" rows="3"><?= htmlspecialchars($membro['observacoes']) ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Salvar Alterações</button>
                <a href="membros.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</main>
<?php require_once '../includes/footer.php'; ?>
