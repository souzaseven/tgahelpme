<?php
// admin/api_membro.php — endpoint AJAX para CRUD de membros
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] !== 'maiara') {
    echo json_encode(['ok' => false, 'msg' => 'Não autorizado.']);
    exit;
}

require_once '../conexao.php';

function resp(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}

$action = trim($_POST['action'] ?? '');

switch ($action) {

    case 'criar':
        $nome = trim($_POST['nome_completo']   ?? '');
        $nasc = trim($_POST['data_nascimento'] ?? '');
        if (!$nome || !$nasc) resp(false, 'Preencha os campos obrigatórios.');

        $stmt = $pdo->prepare(
            "INSERT INTO renascer_menbros
             (nome_completo, data_nascimento, telefone, endereco, data_conversao, status, observacoes, data_cadastro)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        try {
            $stmt->execute([
                $nome,
                $nasc,
                trim($_POST['telefone']       ?? ''),
                trim($_POST['endereco']        ?? ''),
                trim($_POST['data_conversao']  ?? '') ?: null,
                trim($_POST['status']          ?? 'ativo'),
                trim($_POST['observacoes']     ?? ''),
            ]);
            $id = $pdo->lastInsertId();
            $row = $pdo->prepare("SELECT * FROM renascer_menbros WHERE id = ?");
            $row->execute([$id]);
            resp(true, 'Membro cadastrado.', ['membro' => $row->fetch(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            resp(false, 'Erro ao salvar: ' . $e->getMessage());
        }
        break;

    case 'editar':
        $id   = intval($_POST['id']             ?? 0);
        $nome = trim($_POST['nome_completo']    ?? '');
        $nasc = trim($_POST['data_nascimento']  ?? '');
        if (!$id || !$nome || !$nasc) resp(false, 'Dados inválidos.');

        $stmt = $pdo->prepare(
            "UPDATE renascer_menbros
             SET nome_completo=?, data_nascimento=?, telefone=?, endereco=?,
                 data_conversao=?, status=?, observacoes=?
             WHERE id=?"
        );
        try {
            $stmt->execute([
                $nome,
                $nasc,
                trim($_POST['telefone']       ?? ''),
                trim($_POST['endereco']        ?? ''),
                trim($_POST['data_conversao']  ?? '') ?: null,
                trim($_POST['status']          ?? 'ativo'),
                trim($_POST['observacoes']     ?? ''),
                $id,
            ]);
            $row = $pdo->prepare("SELECT * FROM renascer_menbros WHERE id = ?");
            $row->execute([$id]);
            resp(true, 'Dados atualizados.', ['membro' => $row->fetch(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            resp(false, 'Erro ao atualizar: ' . $e->getMessage());
        }
        break;

    case 'excluir':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) resp(false, 'ID inválido.');
        try {
            $pdo->prepare("DELETE FROM renascer_menbros WHERE id = ?")->execute([$id]);
            resp(true, 'Membro excluído.');
        } catch (PDOException $e) {
            resp(false, 'Erro ao excluir: ' . $e->getMessage());
        }
        break;

    default:
        resp(false, 'Ação inválida.');
}
