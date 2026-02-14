<?php

require_once 'conexao.php';



header('Content-Type: application/json');



try {

    $id = $_POST['id'] ?? null;

    $data = $_POST['data'] ?? '';

    $tema = $_POST['tema'] ?? '';

    $texto = $_POST['texto'] ?? '';

    $ministrado_por = $_POST['ministrado_por'] ?? '';



    if (empty($data) || empty($tema)) {

        echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);

        exit;

    }


/*
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
*/

$usuario = $_POST['usuario'] ?? 'desconhecido';

if ($id) {
    // Captura dados antigos antes da atualização
    $oldStmt = $pdo->prepare("SELECT * FROM devocionais WHERE id = :id");
    $oldStmt->execute([':id' => $id]);
    $dadosAnteriores = json_encode($oldStmt->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);

    // Atualizar devocional existente
    $stmt = $pdo->prepare('UPDATE devocionais 
        SET data = :data, tema = :tema, texto = :texto, ministrado_por = :ministrado_por 
        WHERE id = :id');
    $stmt->execute([
        ':data' => $data,
        ':tema' => $tema,
        ':texto' => $texto,
        ':ministrado_por' => $ministrado_por,
        ':id' => $id
    ]);

    $acao = 'editar';
    $devocionalId = $id;
} else {
    // Inserir novo devocional
    $stmt = $pdo->prepare('INSERT INTO devocionais (data, tema, texto, ministrado_por) 
        VALUES (:data, :tema, :texto, :ministrado_por)');
    $stmt->execute([
        ':data' => $data,
        ':tema' => $tema,
        ':texto' => $texto,
        ':ministrado_por' => $ministrado_por
    ]);

    $acao = 'inserir';
    $devocionalId = $pdo->lastInsertId();
    $dadosAnteriores = '';
}

// Dados novos
$dadosNovos = json_encode([
    'data' => $data,
    'tema' => $tema,
    'texto' => $texto,
    'ministrado_por' => $ministrado_por
], JSON_UNESCAPED_UNICODE);

// Grava log
$logStmt = $pdo->prepare("INSERT INTO logs_devocionais 
    (devocional_id, usuario, acao, dados_anteriores, dados_novos) 
    VALUES (:devocional_id, :usuario, :acao, :dados_anteriores, :dados_novos)");
$logStmt->execute([
    ':devocional_id' => $devocionalId,
    ':usuario' => $usuario,
    ':acao' => $acao,
    ':dados_anteriores' => $dadosAnteriores,
    ':dados_novos' => $dadosNovos
]);

echo json_encode(['success' => true, 'message' => 'Devocional salvo com sucesso!']);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);

}

