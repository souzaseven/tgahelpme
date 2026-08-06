<?php
// admin/api_membro.php — endpoint AJAX para CRUD de membros
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/auth.php';
auth_guard_ajax();

require_once '../conexao.php';

define('UPLOADS_DIR', __DIR__ . '/../assets/uploads/fotos/');
define('UPLOADS_URL', '../assets/uploads/fotos/');

function resp(bool $ok, string $msg = '', array $extra = []): void
{
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}

/**
 * Processa o upload da foto e retorna o nome do arquivo salvo ou null.
 * Em caso de erro, seta $erro por referência.
 */
function processarFoto(?string $fotoAtual, string &$erro): ?string
{
    if (empty($_FILES['foto']['tmp_name'])) {
        return $fotoAtual; // Sem novo arquivo: mantém o atual
    }

    $file = $_FILES['foto'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $erro = 'Erro no upload da foto (código ' . $file['error'] . ').';
        return null;
    }

    $maxBytes = 3 * 1024 * 1024; // 3 MB
    if ($file['size'] > $maxBytes) {
        $erro = 'Foto muito grande. Máximo permitido: 3 MB.';
        return null;
    }

    $tipo = mime_content_type($file['tmp_name']);
    $permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($tipo, $permitidos, true)) {
        $erro = 'Formato inválido. Use JPG, PNG, WEBP ou GIF.';
        return null;
    }

    $ext = match($tipo) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg',
    };

    if (!is_dir(UPLOADS_DIR)) {
        mkdir(UPLOADS_DIR, 0755, true);
    }

    $nomeArquivo = uniqid('foto_', true) . '.' . $ext;
    $destino = UPLOADS_DIR . $nomeArquivo;

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        $erro = 'Falha ao salvar a foto no servidor.';
        return null;
    }

    // Remove foto anterior se existir
    if ($fotoAtual && file_exists(UPLOADS_DIR . $fotoAtual)) {
        @unlink(UPLOADS_DIR . $fotoAtual);
    }

    return $nomeArquivo;
}

$action = trim($_POST['action'] ?? '');

switch ($action) {

    case 'criar':
        $nome = trim($_POST['nome_completo']   ?? '');
        $nasc = trim($_POST['data_nascimento'] ?? '');
        if (!$nome || !$nasc) resp(false, 'Preencha os campos obrigatórios.');

        $erroFoto = '';
        $foto = processarFoto(null, $erroFoto);
        if ($erroFoto) resp(false, $erroFoto);

        $stmt = $pdo->prepare(
            "INSERT INTO renascer_menbros
             (nome_completo, data_nascimento, telefone, endereco, data_conversao, status, observacoes, foto, data_cadastro)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
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
                $foto,
            ]);
            $id = $pdo->lastInsertId();
            $row = $pdo->prepare("SELECT * FROM renascer_menbros WHERE id = ?");
            $row->execute([$id]);
            $membro = $row->fetch(PDO::FETCH_ASSOC);
            $membro['foto_url'] = $membro['foto'] ? UPLOADS_URL . $membro['foto'] : null;
            resp(true, 'Membro cadastrado.', ['membro' => $membro]);
        } catch (PDOException $e) {
            resp(false, 'Erro ao salvar: ' . $e->getMessage());
        }
        break;

    case 'editar':
        $id   = intval($_POST['id']             ?? 0);
        $nome = trim($_POST['nome_completo']    ?? '');
        $nasc = trim($_POST['data_nascimento']  ?? '');
        if (!$id || !$nome || !$nasc) resp(false, 'Dados inválidos.');

        // Busca foto atual para poder deletar se houver nova
        $fotoStmt = $pdo->prepare("SELECT foto FROM renascer_menbros WHERE id = ?");
        $fotoStmt->execute([$id]);
        $fotoAtual = $fotoStmt->fetchColumn() ?: null;

        $erroFoto = '';
        $foto = processarFoto($fotoAtual, $erroFoto);
        if ($erroFoto) resp(false, $erroFoto);

        // Se veio flag para remover foto
        if (!empty($_POST['remover_foto']) && $_POST['remover_foto'] === '1') {
            if ($fotoAtual && file_exists(UPLOADS_DIR . $fotoAtual)) {
                @unlink(UPLOADS_DIR . $fotoAtual);
            }
            $foto = null;
        }

        $stmt = $pdo->prepare(
            "UPDATE renascer_menbros
             SET nome_completo=?, data_nascimento=?, telefone=?, endereco=?,
                 data_conversao=?, status=?, observacoes=?, foto=?
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
                $foto,
                $id,
            ]);
            $row = $pdo->prepare("SELECT * FROM renascer_menbros WHERE id = ?");
            $row->execute([$id]);
            $membro = $row->fetch(PDO::FETCH_ASSOC);
            $membro['foto_url'] = $membro['foto'] ? UPLOADS_URL . $membro['foto'] : null;
            resp(true, 'Dados atualizados.', ['membro' => $membro]);
        } catch (PDOException $e) {
            resp(false, 'Erro ao atualizar: ' . $e->getMessage());
        }
        break;

    case 'excluir':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) resp(false, 'ID inválido.');
        try {
            // Remove foto do servidor antes de deletar o registro
            $fotoStmt = $pdo->prepare("SELECT foto FROM renascer_menbros WHERE id = ?");
            $fotoStmt->execute([$id]);
            $fotoArquivo = $fotoStmt->fetchColumn();
            if ($fotoArquivo && file_exists(UPLOADS_DIR . $fotoArquivo)) {
                @unlink(UPLOADS_DIR . $fotoArquivo);
            }
            $pdo->prepare("DELETE FROM renascer_menbros WHERE id = ?")->execute([$id]);
            resp(true, 'Membro excluído.');
        } catch (PDOException $e) {
            resp(false, 'Erro ao excluir: ' . $e->getMessage());
        }
        break;

    default:
        resp(false, 'Ação inválida.');
}
