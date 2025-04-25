<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once 'conexao.php';

// Logs para debug
file_put_contents('debug_post.log', print_r($_POST, true));
file_put_contents('debug_files.log', print_r($_FILES, true));

try {
    // Definir condição de atualização
    $whereClause = '';
    $whereParams = [];

    if (!empty($_POST['id'])) {
        $whereClause = 'id = :id';
        $whereParams[':id'] = $_POST['id'];
    } elseif (!empty($_POST['ticket'])) {
        $whereClause = 'ticket = :ticket';
        $whereParams[':ticket'] = $_POST['ticket'];
    } else {
        echo json_encode(['success' => false, 'error' => 'ID ou número do ticket são obrigatórios para atualizar.']);
        exit;
    }

    // Verifica se o ticket existe
    $stmt = $pdo->prepare("SELECT imagem FROM ticketsmarts WHERE $whereClause");
    $stmt->execute($whereParams);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode(['success' => false, 'error' => 'Ticket não encontrado para atualização']);
        exit;
    }

    $imagem_anterior = $ticket['imagem'];
    $imagem_nova = $imagem_anterior;

    // Processa nova imagem se enviada
    if (isset($_FILES['editImagemAnexo']) && $_FILES['editImagemAnexo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['editImagemAnexo']['name'], PATHINFO_EXTENSION);
        $imagem_nova = uniqid('img_', true) . '.' . $ext;
        $caminho = __DIR__ . '/uploads/' . $imagem_nova;

        if (!move_uploaded_file($_FILES['editImagemAnexo']['tmp_name'], $caminho)) {
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar a nova imagem']);
            exit;
        }

        if ($imagem_anterior && file_exists(__DIR__ . '/uploads/' . $imagem_anterior)) {
            unlink(__DIR__ . '/uploads/' . $imagem_anterior);
        }
    }

    // Atualiza os dados
    $sql = "UPDATE ticketsmarts SET
        ticket = :ticket,
        empresa = :empresa,
        cnpj = :cnpj,
        cod_cliente_tga = :cod_cliente_tga,
        quant_smart = :quant_smart,
        adquirente = :adquirente,
        modelo = :modelo,
        solicitacao = :solicitacao,
        anotacao = :anotacao,
        monitor = :monitor,
        status = :status,
        imagem = :imagem,
        data_atualizacao = NOW()
        WHERE $whereClause";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([
        ':ticket' => $_POST['ticket'],
        ':empresa' => $_POST['empresa'],
        ':cnpj' => $_POST['cnpj'] ?? null,
        ':cod_cliente_tga' => $_POST['cod_cliente_tga'] ?? null,
        ':quant_smart' => $_POST['quant_smart'] ?? 0,
        ':adquirente' => $_POST['adquirente'],
        ':modelo' => $_POST['modelo'],
        ':solicitacao' => $_POST['solicitacao'],
        ':anotacao' => $_POST['anotacao'] ?? null,
        ':monitor' => $_POST['monitor'] ?? null,
        ':status' => $_POST['status'],
        ':imagem' => $imagem_nova
    ], $whereParams));

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}