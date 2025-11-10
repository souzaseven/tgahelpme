<?php
// =============================================
// controle_pausa.php (v1.1)
// Lida com listagem e atualização de status das pausas
// =============================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once 'conexao.php';

$acao = $_GET['acao'] ?? '';

switch ($acao) {

    // ============================================================
    // LISTAR ESTADO ATUAL (GET)
    // ============================================================
    case 'get_estado':
        try {
            $sql = "SELECT 
                        id,
                        nome,
                        lider,
                        status_pausa AS status,
                        inicio_pausa,
                        fim_pausa,
                        tempo_pausa,
                        tempo_excedido,
                        inicio_espera,
                        tempo_espera,
                        dia_pausa,
                        motivo_pausa
                    FROM operadores
                    WHERE lider = 'Daniel Feix'
                    ORDER BY nome ASC";

            $stmt = $pdo->query($sql);
            $dados = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'estado' => $dados
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;

    // ============================================================
    // ATUALIZAR STATUS DE UM OPERADOR (POST)
    // ============================================================
    case 'atualizar_status':
        $input = json_decode(file_get_contents("php://input"), true);
        $nome = trim($input['nome'] ?? '');
        $status = trim($input['status'] ?? '');
        $agora = date('Y-m-d H:i:s');

        if (!$nome || !$status) {
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
            exit;
        }

        try {
            if ($status === 'pausa') {
                $sql = "UPDATE operadores 
                        SET status_pausa='pausa', inicio_pausa=:agora, dia_pausa=CURDATE(), tempo_excedido=0
                        WHERE nome=:nome";
            } elseif ($status === 'espera') {
                $sql = "UPDATE operadores 
                        SET status_pausa='espera', inicio_espera=:agora, tempo_espera=0
                        WHERE nome=:nome";
            } else {
                $sql = "UPDATE operadores 
                        SET status_pausa='disponivel', fim_pausa=:agora
                        WHERE nome=:nome";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':agora' => $agora, ':nome' => $nome]);

            echo json_encode(['success' => true, 'status' => $status]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ============================================================
    // AÇÃO INVÁLIDA
    // ============================================================
    default:
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        break;
}
?>
