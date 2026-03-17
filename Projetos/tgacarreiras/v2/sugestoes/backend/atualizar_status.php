<?php
// ============================================================
//  backend/atualizar_status.php — TGAsys
//  Endpoint AJAX para atualização de status de sugestão
//  Método aceito : POST
//  Retorno       : JSON { success, message, novo_status?, novo_csrf? }
// ============================================================

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexao.php';

/* ── Helper interno: encerra com resposta JSON padronizada ── */
function responder(bool $ok, string $mensagem, int $httpCode = 200): never {
    http_response_code($httpCode);
    echo json_encode(
        ['success' => $ok, 'message' => $mensagem],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/* ═══════════════════════════════════════════════════════════
   BLOCO 1 — MÉTODO HTTP
   Rejeita imediatamente qualquer verbo diferente de POST.
   Feito antes de qualquer leitura de sessão ou banco.
═══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método não permitido.', 405);
}

/* ═══════════════════════════════════════════════════════════
   BLOCO 2 — AUTENTICAÇÃO
   Verifica se existe sessão ativa antes de tocar no banco.
═══════════════════════════════════════════════════════════ */
if (empty($_SESSION['usuario_id'])) {
    responder(false, 'Sessão expirada. Faça login novamente.', 401);
}

/* ═══════════════════════════════════════════════════════════
   BLOCO 3 — PERMISSÃO (somente administradores)
   Valida diretamente no banco para evitar manipulação
   de variáveis de sessão pelo cliente.
═══════════════════════════════════════════════════════════ */
$stmtUser = $pdo->prepare(
    "SELECT is_admin, ativo FROM usuarios_carreiras WHERE id = :id LIMIT 1"
);
$stmtUser->execute([':id' => (int) $_SESSION['usuario_id']]);
$usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$usuario || (int)$usuario['ativo'] !== 1 || (int)$usuario['is_admin'] !== 1) {
    responder(false, 'Você não tem permissão para realizar esta ação.', 403);
}

/* ═══════════════════════════════════════════════════════════
   BLOCO 4 — PROTEÇÃO CSRF
   Compara token enviado com o da sessão usando comparação
   de tempo constante (hash_equals) para evitar timing attack.
═══════════════════════════════════════════════════════════ */
if (
    empty($_POST['csrf_token'])    ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    responder(false, 'Token de segurança inválido. Recarregue a página.', 403);
}

/* ═══════════════════════════════════════════════════════════
   BLOCO 5 — VALIDAÇÃO DOS DADOS RECEBIDOS
   ⚠️  $statusPermitidos deve espelhar exatamente o
       $statusMap de view.php e listar.php.
       Chave = valor gravado no banco de dados.
═══════════════════════════════════════════════════════════ */
$id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$status = strtolower(trim($_POST['status'] ?? ''));

if ($id <= 0) {
    responder(false, 'ID de sugestão inválido.', 400);
}

$statusPermitidos = [
    'aberto',
    'em_analise',
    'em_desenvolvimento',
    'implementado',
    'rejeitado',
];

if (!in_array($status, $statusPermitidos, true)) {
    responder(false, 'Status inválido: "' . htmlspecialchars($status) . '".', 400);
}

/* ═══════════════════════════════════════════════════════════
   BLOCO 6 — PROCESSAMENTO PRINCIPAL
═══════════════════════════════════════════════════════════ */
try {

    /* 6a. Confirma existência da sugestão e lê status atual */
    $stmtAtual = $pdo->prepare(
        "SELECT status FROM sugestoes_tgacarreiras WHERE id = :id LIMIT 1"
    );
    $stmtAtual->execute([':id' => $id]);
    $statusAtual = $stmtAtual->fetchColumn();

    if ($statusAtual === false) {
        responder(false, 'Sugestão não encontrada.', 404);
    }

    /* 6b. Sem mudança — retorna sucesso sem gravar no banco */
    if ($statusAtual === $status) {
        $labelMap = [
            'aberto'             => 'Aberto',
            'em_analise'         => 'Em Análise',
            'em_desenvolvimento' => 'Em Desenvolvimento',
            'implementado'       => 'Implementado',
            'rejeitado'          => 'Rejeitado',
        ];
        $label = $labelMap[$status] ?? $status;
        responder(true, "O status já está como \"{$label}\". Nenhuma alteração feita.");
    }

    /* 6c. Atualiza status e timestamp no banco */
    $stmtUpdate = $pdo->prepare("
        UPDATE sugestoes_tgacarreiras
           SET status        = :status,
               atualizado_em = NOW()
         WHERE id            = :id
    ");
    $stmtUpdate->execute([':status' => $status, ':id' => $id]);

    /* 6d. Histórico de alterações (isolado — não interrompe o fluxo
           principal se a tabela não existir no ambiente) */
    try {
        $stmtHist = $pdo->prepare("
            INSERT INTO sugestoes_historico
                (sugestao_id, usuario_id, status_anterior, status_novo, criado_em)
            VALUES
                (:sid, :uid, :anterior, :novo, NOW())
        ");
        $stmtHist->execute([
            ':sid'      => $id,
            ':uid'      => (int) $_SESSION['usuario_id'],
            ':anterior' => $statusAtual,
            ':novo'     => $status,
        ]);
    } catch (PDOException $eHist) {
        // Histórico é opcional: loga mas não falha a requisição
        error_log('[TGAsys] Histórico não registrado: ' . $eHist->getMessage());
    }

    /* 6e. Rotaciona o CSRF após ação bem-sucedida e devolve o
           novo token para o frontend atualizar o campo hidden */
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    echo json_encode([
        'success'     => true,
        'message'     => 'Status atualizado com sucesso!',
        'novo_status' => $status,
        'novo_csrf'   => $_SESSION['csrf_token'],
    ], JSON_UNESCAPED_UNICODE);
    exit;

/* ═══════════════════════════════════════════════════════════
   BLOCO 7 — TRATAMENTO DE ERROS
   Erros de banco são logados no servidor e nunca expostos
   ao cliente (segurança + UX).
═══════════════════════════════════════════════════════════ */
} catch (PDOException $e) {
    error_log('[TGAsys] atualizar_status PDO: ' . $e->getMessage());
    responder(false, 'Erro interno ao salvar. Tente novamente em instantes.', 500);
} catch (Exception $e) {
    responder(false, $e->getMessage(), 400);
}