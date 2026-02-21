<?php
include "conexao.php";

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$table  = $_GET['table']  ?? '';
$id     = $_GET['id']     ?? null;

// Mapeamento fila → evolux_queue_id
const FILA_QUEUE_MAP = [
    'Suporte Matriz'         => 9,
    'Fila Matriz Chat/Whats' => 109,
];

$allowedTables = ['usuarios', 'operadores', 'devocionais'];
if (!in_array($table, $allowedTables) && !in_array($action, ['update_fila_lider', 'update_password', 'listar_tabelas'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tabela não permitida']);
    exit;
}

if (in_array($action, ['get', 'update', 'delete']) && empty($id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID é obrigatório']);
    exit;
}

try {
    switch ($action) {

        case 'listar_tabelas':
            echo json_encode(['success' => true, 'data' => listarTabelas($pdo)]);
            break;

        case 'select':
            $page   = max(1, intval($_GET['page']  ?? 1));
            $limit  = min(100, max(1, intval($_GET['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;

            $stmt = $pdo->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT ? OFFSET ?");
            $stmt->execute([$limit, $offset]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();

            echo json_encode([
                'success' => true, 'data' => $rows,
                'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => ceil($total / $limit)]
            ]);
            break;

        case 'get':
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Registro não encontrado');
            echo json_encode(['success' => true, 'data' => $row]);
            break;

        case 'insert':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!$input || !is_array($input)) throw new Exception('Dados JSON inválidos');

            if ($table === 'operadores') $input = enriquecerOperador($input);

            $input = sanitizeData($input, $table);
            if (empty($input)) throw new Exception('Nenhum dado válido para inserção');

            if ($table === 'usuarios' && !isset($input['data_cadastro'])) {
                $input['data_cadastro'] = date('Y-m-d H:i:s');
            }

            $cols = implode(",", array_keys($input));
            $placeholders = implode(",", array_fill(0, count($input), "?"));
            $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($placeholders)");
            $stmt->execute(array_values($input));
            $lastId = $pdo->lastInsertId();

            logAction($pdo, 'INSERT', $table, $lastId, json_encode($input));

            if ($table === 'operadores' && !empty($input['evolux_agent_id'])) {
                sincronizarInsert($pdo, $input);
            }

            echo json_encode(['success' => true, 'message' => 'Registro inserido com sucesso', 'id' => $lastId]);
            break;

        case 'update':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!$input || !is_array($input)) throw new Exception('Dados JSON inválidos');

            if ($table === 'operadores') $input = enriquecerOperador($input);

            $input = sanitizeData($input, $table);
            if (empty($input)) throw new Exception('Nenhum dado válido para atualização');

            $set = implode(",", array_map(fn($k) => "$k=?", array_keys($input)));
            $stmt = $pdo->prepare("UPDATE $table SET $set WHERE id=?");
            $stmt->execute([...array_values($input), $id]);
            $affected = $stmt->rowCount();

            if ($affected === 0) throw new Exception('Nenhum registro foi atualizado');

            logAction($pdo, 'UPDATE', $table, $id, json_encode($input));

            if ($table === 'operadores') sincronizarUpdate($pdo, $input, $id);

            echo json_encode(['success' => true, 'message' => 'Registro atualizado com sucesso', 'affected' => $affected]);
            break;

        case 'delete':
            $check = $pdo->prepare("SELECT id, evolux_agent_id FROM $table WHERE id = ?");
            $check->execute([$id]);
            $row = $check->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Registro não encontrado']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM $table WHERE id=?");
            $stmt->execute([$id]);
            $affected = $stmt->rowCount();

            logAction($pdo, 'DELETE', $table, $id);

            if ($table === 'operadores' && !empty($row['evolux_agent_id'])) {
                sincronizarDelete($pdo, $row['evolux_agent_id']);
            }

            echo json_encode(['success' => true, 'message' => 'Registro excluído com sucesso', 'affected' => $affected]);
            break;

        case 'update_password':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['senha']) || empty(trim($input['senha']))) throw new Exception('Nova senha é obrigatória');
            $senha = trim($input['senha']);
            if (strlen($senha) < 4) throw new Exception('A senha deve ter pelo menos 4 caracteres');

            $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE nome = 'SUPORTE'");
            $stmt->execute([$senha]);
            logAction($pdo, 'UPDATE_PASSWORD', 'usuarios', null, 'Senha do SUPORTE atualizada');
            echo json_encode(['success' => true, 'message' => 'Senha atualizada com sucesso', 'affected' => $stmt->rowCount()]);
            break;

        case 'update_fila_lider':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['lider']) || !isset($input['fila'])) throw new Exception('Líder e fila são obrigatórios');

            $lider = trim($input['lider']);
            $fila  = trim($input['fila']);

            $lideresPermitidos = ['Alex Sandro Braulio', 'Daniel Feix', 'Willian Pereira Reis'];
            if (!in_array($lider, $lideresPermitidos) || !array_key_exists($fila, FILA_QUEUE_MAP)) {
                throw new Exception('Líder ou fila inválidos');
            }

            $queueId = FILA_QUEUE_MAP[$fila];
            $stmt = $pdo->prepare("UPDATE operadores SET fila = ?, evolux_queue_id = ? WHERE lider = ?");
            $stmt->execute([$fila, $queueId, $lider]);
            $affected = $stmt->rowCount();

            logAction($pdo, 'UPDATE_FILA_LIDER', 'operadores', null, "Líder: $lider, Fila: $fila, QueueID: $queueId, Afetados: $affected");
            echo json_encode(['success' => true, 'message' => "Fila atualizada para $affected operador(es) do líder: $lider", 'affected' => $affected]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }

} catch (Exception $e) {
    error_log("Erro API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}


// ══════════════════════════════════════════════════════════
//  ENRIQUECIMENTO AUTOMÁTICO DO OPERADOR
// ══════════════════════════════════════════════════════════

/**
 * Extrai evolux_agent_id do link automaticamente.
 * Exemplo: https://tgasistemas.evolux.io/callcenter/agent/edit/95  →  95
 * Preenche evolux_queue_id de acordo com a fila selecionada.
 */
function enriquecerOperador(array $input): array {

    // 1. Extrair evolux_agent_id do link
    if (!empty($input['link'])) {
        if (preg_match('/\/edit\/(\d+)\s*$/', trim($input['link']), $matches)) {
            $input['evolux_agent_id'] = (int) $matches[1];
        }
    }

    // 2. Preencher evolux_queue_id pela fila
    if (!empty($input['fila']) && isset(FILA_QUEUE_MAP[$input['fila']])) {
        $input['evolux_queue_id'] = FILA_QUEUE_MAP[$input['fila']];
    }

    return $input;
}


// ══════════════════════════════════════════════════════════
//  SINCRONIZAÇÃO — controle_pausa
// ══════════════════════════════════════════════════════════

function sincronizarInsert(PDO $pdo, array $input): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tgamea80_SUPORTE.controle_pausa
                (nome_usuario, equipe, evolux_agent_id, status,
                 tempo_total_pausa, tempo_total_espera, ultima_atualizacao,
                 notificacao_enviada, is_admin, elider,
                 pref_toast, pref_fala, pref_audio, pref_notif, pref_voz,
                 pref_alvo, pref_alerta_alvo, pref_volume_fala,
                 whatsapp_optin, whatsapp_limite_enviado, whatsapp_habilitado,
                 whatsapp_tentativas, pref_tema, modo_pausa,
                 logatodasequipes, tipo_pausa, pausa_manual, forcada)
            VALUES
                (?, ?, ?, 'ativo',
                 0, 0, NOW(),
                 0, 0, 0,
                 1, 0, 1, 1, 'C',
                 'todos', 'todos', 10,
                 0, 0, 1,
                 0, 'escuro', 'automatica',
                 1, 'EVOLUX', 0, 0)
            ON DUPLICATE KEY UPDATE
                nome_usuario       = VALUES(nome_usuario),
                equipe             = VALUES(equipe),
                ultima_atualizacao = NOW()
        ");
        $stmt->execute([$input['nome'], $input['lider'], $input['evolux_agent_id']]);
    } catch (Exception $e) {
        error_log("Erro sincronizarInsert controle_pausa: " . $e->getMessage());
    }
}

function sincronizarUpdate(PDO $pdo, array $input, $operadorId): void {
    try {
        $stmt = $pdo->prepare("SELECT evolux_agent_id FROM operadores WHERE id = ?");
        $stmt->execute([$operadorId]);
        $evoluxId = $stmt->fetchColumn();
        if (empty($evoluxId)) return;

        $campos = []; $valores = [];

        if (isset($input['nome']))  { $campos[] = "nome_usuario = ?"; $valores[] = $input['nome']; }
        if (isset($input['lider'])) { $campos[] = "equipe = ?";       $valores[] = $input['lider']; }
        if (empty($campos)) return;

        $campos[]  = "ultima_atualizacao = NOW()";
        $valores[] = $evoluxId;

        $stmt = $pdo->prepare("UPDATE tgamea80_SUPORTE.controle_pausa SET " . implode(', ', $campos) . " WHERE evolux_agent_id = ?");
        $stmt->execute($valores);
    } catch (Exception $e) {
        error_log("Erro sincronizarUpdate controle_pausa: " . $e->getMessage());
    }
}

function sincronizarDelete(PDO $pdo, $evoluxAgentId): void {
    try {
        $stmt = $pdo->prepare("DELETE FROM tgamea80_SUPORTE.controle_pausa WHERE evolux_agent_id = ?");
        $stmt->execute([$evoluxAgentId]);
    } catch (Exception $e) {
        error_log("Erro sincronizarDelete controle_pausa: " . $e->getMessage());
    }
}


// ══════════════════════════════════════════════════════════
//  LISTAR TABELAS
// ══════════════════════════════════════════════════════════

function listarTabelas(PDO $pdo): array {
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $info = [];
        foreach ($tables as $table) {
            $estrutura      = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
            $totalRegistros = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            $info[$table]   = ['estrutura' => $estrutura, 'total_registros' => $totalRegistros, 'campos' => count($estrutura)];
        }
        return $info;
    } catch (Exception $e) {
        error_log("Erro listarTabelas: " . $e->getMessage());
        return [];
    }
}
?>