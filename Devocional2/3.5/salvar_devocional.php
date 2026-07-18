<?php

// Endpoint chamado pelo formulário do modal (handleSalvarDevocional em script.js)
// para inserir um devocional novo ou atualizar um existente
require_once 'conexao.php';



header('Content-Type: application/json');



try {

    // Dados recebidos do formulário via POST (id só vem preenchido em edição)
    $id = $_POST['id'] ?? null;

    $data = $_POST['data'] ?? '';

    $tema = $_POST['tema'] ?? '';

    $texto = $_POST['texto'] ?? '';

    $ministrado_por = $_POST['ministrado_por'] ?? '';



    // Validação básica: data e tema são obrigatórios
    if (empty($data) || empty($tema)) {

        echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);

        exit;

    }


// Bloco antigo desativado: versão anterior deste endpoint, sem gravação de log.
// Mantido comentado como referência histórica.
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

// Usuário responsável pela ação, usado apenas para registro no log
$usuario = $_POST['usuario'] ?? 'desconhecido';

if ($id) {
    // ===== Fluxo de EDIÇÃO =====
    // Captura dados antigos antes da atualização, para guardar no log o "antes"
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
    // ===== Fluxo de INSERÇÃO =====
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

// Monta o snapshot dos dados novos (estado após a gravação) para o log
$dadosNovos = json_encode([
    'data' => $data,
    'tema' => $tema,
    'texto' => $texto,
    'ministrado_por' => $ministrado_por
], JSON_UNESCAPED_UNICODE);

// Grava log de auditoria com quem fez o quê, e o "antes"/"depois" dos dados
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

// Confirma sucesso ao front-end
echo json_encode(['success' => true, 'message' => 'Devocional salvo com sucesso!']);

} catch (Exception $e) {

    // Qualquer falha (SQL, conexão, etc.) cai aqui e retorna HTTP 500
    http_response_code(500);

    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);

}

