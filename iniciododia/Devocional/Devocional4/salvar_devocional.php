<?php
require_once 'conexao.php';

header('Content-Type: application/json');

try {
    $id = $_POST['id'] ?? null;
    $data = $_POST['data'] ?? '';
    $tema = $_POST['tema'] ?? '';
    $texto = $_POST['texto'] ?? '';
    $ministrado_por = $_POST['ministrado_por'] ?? '';

    if (empty($data) || empty($tema) || empty($ministrado_por)) {
        echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
        exit;
    }

    if ($id) {
        // Atualizar devocional existente
        $stmt = $pdo->prepare('UPDATE devocionais SET data = :data, tema = :tema, texto = :texto, ministrado_por = :ministrado_por WHERE id = :id');
        $stmt->execute([
            ':data' => $data,
            ':tema' => $tema,
            ':texto' => $texto,
            ':ministrado_por' => $ministrado_por,
            ':id' => $id
        ]);
    } else {
        // Inserir novo devocional
        $stmt = $pdo->prepare('INSERT INTO devocionais (data, tema, texto, ministrado_por) VALUES (:data, :tema, :texto, :ministrado_por)');
        $stmt->execute([
            ':data' => $data,
            ':tema' => $tema,
            ':texto' => $texto,
            ':ministrado_por' => $ministrado_por
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Devocional salvo com sucesso!']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
