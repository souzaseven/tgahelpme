<?php
header('Content-Type: application/json');

require_once __DIR__ . '/conexao.php';

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }

    // ── Sanitização ─────────────────────────────
    $tipo        = trim($_POST['tipo'] ?? '');
    $prioridade  = trim($_POST['prioridade'] ?? 'media');
    $modulo      = trim($_POST['modulo'] ?? '');
    $titulo      = trim($_POST['titulo'] ?? '');
    $descricao   = trim($_POST['descricao'] ?? '');
    $nome        = trim($_POST['nome_solicitante'] ?? '');
    $link        = trim($_POST['link_externo'] ?? '');

    // ── Validação ───────────────────────────────
    if (!$tipo) {
        throw new Exception('Tipo é obrigatório');
    }

    if (!$titulo) {
        throw new Exception('Título é obrigatório');
    }

    if (!$descricao) {
        throw new Exception('Descrição é obrigatória');
    }

    if (strlen($titulo) > 100) {
        throw new Exception('Título muito longo');
    }

    if (strlen($descricao) > 2000) {
        throw new Exception('Descrição muito longa');
    }

    // ── Upload de imagens ───────────────────────
    $anexosSalvos = [];

    if (!empty($_FILES['anexos']['name'][0])) {

        $uploadDir = __DIR__ . '/../uploads/anexos/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($_FILES['anexos']['tmp_name'] as $key => $tmpName) {

            if ($_FILES['anexos']['error'][$key] !== UPLOAD_ERR_OK) {
                continue;
            }

            $originalName = $_FILES['anexos']['name'][$key];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed)) {
                continue;
            }

            $newName = uniqid('anexo_', true) . '.' . $ext;
            $destino = $uploadDir . $newName;

            if (move_uploaded_file($tmpName, $destino)) {
                $anexosSalvos[] = $newName;
            }
        }
    }

    $anexosJson = !empty($anexosSalvos)
        ? json_encode($anexosSalvos, JSON_UNESCAPED_UNICODE)
        : null;

    // ── Insert no banco ─────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO sugestoes_tgacarreiras
        (tipo, titulo, descricao, nome_solicitante, prioridade, modulo, link_externo, anexos)
        VALUES
        (:tipo, :titulo, :descricao, :nome, :prioridade, :modulo, :link, :anexos)
    ");

    $stmt->execute([
        ':tipo'       => $tipo,
        ':titulo'     => $titulo,
        ':descricao'  => $descricao,
        ':nome'       => $nome ?: null,
        ':prioridade' => $prioridade,
        ':modulo'     => $modulo ?: null,
        ':link'       => $link ?: null,
        ':anexos'     => $anexosJson
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Sugestão criada com sucesso'
    ]);

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
