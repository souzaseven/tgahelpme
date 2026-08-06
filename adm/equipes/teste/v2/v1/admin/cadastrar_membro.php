<?php
// admin/cadastrar_membro.php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'maiara') {
    header('Location: login.php');
    exit;
}
require_once '../conexao.php';

$erro = '';
$sucesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $data_conversao = trim($_POST['data_conversao'] ?? '');
    $status = trim($_POST['status'] ?? 'ativo');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($nome_completo && $data_nascimento) {
        $stmt = $pdo->prepare("INSERT INTO renascer_menbros (nome_completo, data_nascimento, telefone, endereco, data_conversao, status, observacoes, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        try {
            $stmt->execute([
                $nome_completo,
                $data_nascimento,
                $telefone,
                $endereco,
                $data_conversao ?: null,
                $status,
                $observacoes
            ]);
            $sucesso = 'Membro cadastrado com sucesso!';
        } catch (PDOException $e) {
            $erro = 'Erro ao cadastrar: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        $erro = 'Preencha os campos obrigatórios.';
    }
}

$titulo_pagina = 'Cadastrar Membro - Igreja Renascer';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<main class="dashboard">
    <div class="topbar">
        <h1>Cadastrar Novo Membro</h1>
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
                <input type="text" name="nome_completo" id="nome_completo" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="data_nascimento">Data de Nascimento *</label>
                <input type="date" name="data_nascimento" id="data_nascimento" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="telefone">Telefone</label>
                <input type="text" name="telefone" id="telefone" class="form-control">
            </div>

            <div class="form-group">
                <label for="endereco">Endereço</label>
                <textarea name="endereco" id="endereco" class="form-control" rows="2"></textarea>
            </div>

            <div class="form-group">
                <label for="data_conversao">Data de Conversão</label>
                <input type="date" name="data_conversao" id="data_conversao" class="form-control">
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </div>

            <div class="form-group">
                <label for="observacoes">Observações</label>
                <textarea name="observacoes" id="observacoes" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Cadastrar</button>
                <a href="membros.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</main>
<?php require_once '../includes/footer.php'; ?>
