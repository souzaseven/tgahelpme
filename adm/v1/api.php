<?php
include "conexao.php";

// Headers de segurança CORS e tipo de conteúdo
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Max-Age: 86400');

// Responder a requisições OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}



// Validar método HTTP
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$table = $_GET['table'] ?? '';
$id = $_GET['id'] ?? null;

// Tabelas permitidas
$allowedTables = ['usuarios', 'operadores', 'devocionais'];
if (!in_array($table, $allowedTables) && $action !== 'update_fila_lider' && $action !== 'update_password' && $action !== 'listar_tabelas') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tabela não permitida']);
    exit;
}

// Validar ID quando necessário
if (in_array($action, ['get', 'update', 'delete']) && empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID é obrigatório']);
    exit;
}



try {
    switch ($action) {
  case 'listar_tabelas':
        $tabelas = listarTabelas($pdo);
        
        echo json_encode([
            'success' => true, 
            'data' => $tabelas
        ]);
        break;
        case 'select':
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;
            
            // Buscar com paginação
            $stmt = $pdo->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar total
            $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
            $total = $totalStmt->fetchColumn();
            
            echo json_encode([
                'success' => true, 
                'data' => $rows,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;

        case 'get':
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                throw new Exception('Registro não encontrado');
            }
            
            echo json_encode(['success' => true, 'data' => $row]);
            break;

        case 'insert':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!$input || !is_array($input)) {
                throw new Exception('Dados JSON inválidos');
            }
            
            // Sanitizar dados
            $input = sanitizeData($input, $table);
            
            if (empty($input)) {
                throw new Exception('Nenhum dado válido para inserção');
            }
            
            // Adicionar data_cadastro automaticamente se não for fornecida
            if ($table === 'usuarios' && !isset($input['data_cadastro'])) {
                $input['data_cadastro'] = date('Y-m-d H:i:s');
            }
            
            $cols = implode(",", array_keys($input));
            $placeholders = implode(",", array_fill(0, count($input), "?"));
            $sql = "INSERT INTO $table ($cols) VALUES ($placeholders)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($input));
            
            $lastId = $pdo->lastInsertId();
            
            // Registrar log
            logAction($pdo, 'INSERT', $table, $lastId, json_encode($input));
            
            echo json_encode([
                'success' => true, 
                'message' => 'Registro inserido com sucesso',
                'id' => $lastId
            ]);
            break;

        case 'update':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!$input || !is_array($input)) {
                throw new Exception('Dados JSON inválidos');
            }
            
            // Sanitizar dados
            $input = sanitizeData($input, $table);
            
            if (empty($input)) {
                throw new Exception('Nenhum dado válido para atualização');
            }
            
            $set = implode(",", array_map(fn($k) => "$k=?", array_keys($input)));
            $sql = "UPDATE $table SET $set WHERE id=?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([...array_values($input), $id]);
            
            $affected = $stmt->rowCount();
            
            if ($affected === 0) {
                throw new Exception('Nenhum registro foi atualizado');
            }
            
            // Registrar log
            logAction($pdo, 'UPDATE', $table, $id, json_encode($input));
            
            echo json_encode([
                'success' => true, 
                'message' => 'Registro atualizado com sucesso',
                'affected' => $affected
            ]);
            break;

        case 'delete':
            // Verificar se o registro existe antes de deletar
            $check = $pdo->prepare("SELECT id FROM $table WHERE id = ?");
            $check->execute([$id]);
            
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id=?");
            $stmt->execute([$id]);
            
            $affected = $stmt->rowCount();
            
            // Registrar log
            logAction($pdo, 'DELETE', $table, $id);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Registro excluído com sucesso',
                'affected' => $affected
            ]);
            break;

        // NOVAS FUNCIONALIDADES
        case 'update_password':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['senha']) || empty(trim($input['senha']))) {
                throw new Exception('Nova senha é obrigatória');
            }
            
            $senha = trim($input['senha']);
            
            // Validar força da senha
            if (strlen($senha) < 4) {
                throw new Exception('A senha deve ter pelo menos 4 caracteres');
            }
            
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE nome = 'SUPORTE'");
            $stmt->execute([$senha]);
            
            $affected = $stmt->rowCount();
            
            // Registrar log
            logAction($pdo, 'UPDATE_PASSWORD', 'usuarios', null, 'Senha do SUPORTE atualizada');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Senha do usuário SUPORTE atualizada com sucesso',
                'affected' => $affected
            ]);
            break;

        case 'update_fila_lider':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['lider']) || !isset($input['fila'])) {
                throw new Exception('Líder e fila são obrigatórios');
            }
            
            $lider = trim($input['lider']);
            $fila = trim($input['fila']);
            
            // Validar valores
            $lideresPermitidos = ['Alex Sandro Braulio', 'Daniel Feix', 'Willian Pereira Reis'];
            $filasPermitidas = ['Suporte Matriz', 'Fila Matriz Chat/Whats'];
            
            if (!in_array($lider, $lideresPermitidos) || !in_array($fila, $filasPermitidas)) {
                throw new Exception('Líder ou fila inválidos');
            }
            
            // CORREÇÃO: Executar o UPDATE diretamente na tabela operadores
            $stmt = $pdo->prepare("UPDATE operadores SET fila = ? WHERE lider = ?");
            $stmt->execute([$fila, $lider]);
            
            $affected = $stmt->rowCount();
            
            // Registrar log
            logAction($pdo, 'UPDATE_FILA_LIDER', 'operadores', null, "Líder: $lider, Fila: $fila, Afetados: $affected");
            
            echo json_encode([
                'success' => true, 
                'message' => "Fila atualizada com sucesso para $affected operador(es) do líder: $lider",
                'affected' => $affected
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
} catch (Exception $e) {
    error_log("Erro API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno: ' . $e->getMessage(),
        'csrf_token' => $_SESSION['csrf_token']
    ]);
}

// Função para listar todas as tabelas do banco
function listarTabelas($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $tabelasInfo = [];
        foreach ($tables as $table) {
            // Obter estrutura da tabela
            $stmt = $pdo->query("DESCRIBE $table");
            $estrutura = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar registros
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
            $totalRegistros = $stmt->fetchColumn();
            
            $tabelasInfo[$table] = [
                'estrutura' => $estrutura,
                'total_registros' => $totalRegistros,
                'campos' => count($estrutura)
            ];
        }
        
        return $tabelasInfo;
    } catch (Exception $e) {
        error_log("Erro ao listar tabelas: " . $e->getMessage());
        return [];
    }
}



?>
