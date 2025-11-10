<?php
// =============================================================
// controle_pausa.php (v2.5) - Seguro + Fila Inteligente + Nome Parcial + Sessão Admin
// =============================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'conexao.php';
session_start();

$acao = $_GET['acao'] ?? $_POST['acao'] ?? null;
$input = json_decode(file_get_contents("php://input"), true) ?: [];

$nomeEntrada = trim($input['nome'] ?? $_GET['nome'] ?? '');
$solicitante = trim($input['solicitante'] ?? '');
$isAdminFlag = isset($input['admin']) && $input['admin'] === true;
$maxPausas = 2;

// =============================================================
// 🔹 Resolve nome parcial → nome completo (case-insensitive)
// =============================================================
function resolverNomeCompleto(PDO $pdo, string $entrada): ?string {
    if ($entrada === '') return null;

    $entrada = mb_strtolower($entrada, 'UTF-8');

    // 1) Tenta nome que começa com
    $sql = "SELECT nome FROM tgamea80_SUPORTE.operadores 
            WHERE LOWER(nome) LIKE :prefix
            ORDER BY LENGTH(nome) ASC LIMIT 3";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':prefix' => $entrada . '%']);
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($result) === 1) return $result[0];

    // 2) Tenta nome que contém
    $sql2 = "SELECT nome FROM tgamea80_SUPORTE.operadores 
             WHERE LOWER(nome) LIKE :like
             ORDER BY LENGTH(nome) ASC LIMIT 3";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([':like' => '%' . $entrada . '%']);
    $result2 = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    if (count($result2) === 1) return $result2[0];

    return null;
}

// =============================================================
// 🔹 Admin autenticado via sessão
// =============================================================
function validarAdminSessao(): bool {
    return !empty($_SESSION['admin_autenticado']) &&
           $_SESSION['admin_autenticado'] === true &&
           ($_SESSION['admin_user'] ?? '') === 'anderson';
}

// =============================================================
// 🔹 Contar pausas ativas
// =============================================================
function contarPausasAtivas(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM tgamea80_SUPORTE.operadores WHERE status_pausa='pausa'")->fetchColumn();
}

// =============================================================
// 🔹 Obter primeiro da fila
// =============================================================
function primeiroDaFila(PDO $pdo): ?string {
    $stmt = $pdo->query("SELECT nome FROM tgamea80_SUPORTE.operadores 
                         WHERE status_pausa='espera' 
                         ORDER BY inicio_espera ASC LIMIT 1");
    return $stmt->fetchColumn();
}

// =============================================================
// 🔹 Verifica existência do operador (nome exato)
// =============================================================
function operadorExiste(PDO $pdo, string $nome): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tgamea80_SUPORTE.operadores WHERE nome=:n");
    $stmt->execute([':n' => $nome]);
    return $stmt->fetchColumn() > 0;
}

// =============================================================
// 🔧 Fluxo principal
// =============================================================
try {
    switch ($acao) {

        // ---------------------------------------------------------
        // 🔹 GET ESTADO
        // ---------------------------------------------------------
        case 'get_estado':
            $sql = "SELECT 
                        id, nome, lider,
                        status_pausa AS status,
                        inicio_pausa, fim_pausa,
                        tempo_pausa, tempo_excedido,
                        inicio_espera, tempo_espera,
                        dia_pausa, motivo_pausa, ultima_atualizacao
                    FROM tgamea80_SUPORTE.operadores
                    WHERE lider='Daniel Feix'
                    ORDER BY nome ASC";
            $stmt = $pdo->query($sql);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'estado' => $dados], JSON_UNESCAPED_UNICODE);
            break;

        // ---------------------------------------------------------
        // 🔹 ENTRAR NA FILA
        // ---------------------------------------------------------
        case 'entrar_fila':
            $nomeReal = resolverNomeCompleto($pdo, $nomeEntrada);
            if (!$nomeReal) throw new Exception("Operador não encontrado ($nomeEntrada).");

            $stmt = $pdo->prepare("SELECT status_pausa FROM tgamea80_SUPORTE.operadores WHERE nome=:nome");
            $stmt->execute([':nome' => $nomeReal]);
            $statusAtual = $stmt->fetchColumn();

            if (in_array($statusAtual, ['espera', 'pausa'])) {
                throw new Exception("Você já está na fila ou em pausa.");
            }

            $sql = "UPDATE tgamea80_SUPORTE.operadores 
                    SET status_pausa='espera', inicio_espera=NOW(), tempo_espera=0, ultima_atualizacao=NOW()
                    WHERE nome=:nome";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nome' => $nomeReal]);

            echo json_encode(['success' => true, 'msg' => "$nomeReal entrou na fila de espera."]);
            break;

        // ---------------------------------------------------------
        // 🔹 ENTRAR EM PAUSA (1º da fila + vaga disponível)
        // ---------------------------------------------------------
        case 'entrar_pausa':
            $nomeReal = resolverNomeCompleto($pdo, $nomeEntrada);
            if (!$nomeReal) throw new Exception("Operador não encontrado ($nomeEntrada).");

            if (contarPausasAtivas($pdo) >= $maxPausas)
                throw new Exception("Limite de pausas simultâneas ($maxPausas) atingido.");

            $primeiro = primeiroDaFila($pdo);
            if (!$primeiro || strcasecmp($primeiro, $nomeReal) !== 0)
                throw new Exception("Apenas o primeiro da fila pode iniciar pausa.");

            $sql = "UPDATE tgamea80_SUPORTE.operadores 
                    SET status_pausa='pausa', inicio_pausa=NOW(), dia_pausa=CURDATE(), tempo_excedido=0, ultima_atualizacao=NOW()
                    WHERE nome=:nome";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nome' => $nomeReal]);

            echo json_encode(['success' => true, 'msg' => "$nomeReal iniciou a pausa."]);
            break;

        // ---------------------------------------------------------
        // 🔹 FORÇAR PAUSA (apenas admin autenticado)
        // ---------------------------------------------------------
        case 'forcar_pausa':
            if (!$isAdminFlag || !validarAdminSessao()) {
                throw new Exception("Ação restrita ao administrador autenticado.");
            }

            $nomeReal = resolverNomeCompleto($pdo, $nomeEntrada);
            if (!$nomeReal) throw new Exception("Operador não encontrado ($nomeEntrada).");

            if (contarPausasAtivas($pdo) >= $maxPausas)
                throw new Exception("Limite de pausas simultâneas já atingido.");

            $sql = "UPDATE tgamea80_SUPORTE.operadores 
                    SET status_pausa='pausa', inicio_pausa=NOW(), dia_pausa=CURDATE(), tempo_excedido=0, ultima_atualizacao=NOW()
                    WHERE nome=:nome";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nome' => $nomeReal]);

            echo json_encode(['success' => true, 'msg' => "$nomeReal foi colocado em pausa forçada."]);
            break;

        // ---------------------------------------------------------
        // 🔹 VOLTAR DISPONÍVEL
        // ---------------------------------------------------------
        case 'voltar_disponivel':
            $nomeReal = resolverNomeCompleto($pdo, $nomeEntrada);
            if (!$nomeReal) throw new Exception("Operador não encontrado ($nomeEntrada).");

            $sql = "UPDATE tgamea80_SUPORTE.operadores 
                    SET status_pausa='disponivel', fim_pausa=NOW(), ultima_atualizacao=NOW()
                    WHERE nome=:nome";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nome' => $nomeReal]);

            echo json_encode(['success' => true, 'msg' => "$nomeReal voltou ao status disponível."]);
            break;

        // ---------------------------------------------------------
        default:
            echo json_encode(['success' => false, 'error' => 'Ação inválida.']);
    }

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
