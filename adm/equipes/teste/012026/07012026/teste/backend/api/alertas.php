<?php
// ============================================================
// alertas.php — Central de alertas (tela Alertas + sino do topo).
// Além do CRUD normal (usuário também pode criar alerta manual,
// tipo 'custom'), tem a ação 'gerar', que dispara a varredura
// automática (gerarAlertasAuto(), em backend/helpers/alertas_auto.php):
// varre faturas de cartão, parcelas de empréstimo e metas com prazo
// próximo, e cria um alerta pra cada um — sem duplicar se já existir
// um alerta não lido criado hoje pra aquela mesma referência
// (referencia_tipo + referencia_id).
//
// Essa varredura já roda sozinha a cada carregamento de página (ver
// index.php), então o usuário nunca precisa clicar em nada pra ela
// acontecer — a ação 'gerar' aqui só existe pra um botão "Verificar
// agora" opcional, pra quem quiser forçar uma atualização na hora.
//
// GET  ?acao=listar              — lista com filtros de nível/tipo/lido
// POST acao=salvar               — cria ou edita um alerta manual
// POST acao=marcar_lido          — marca um (ou todos) como lido
// POST acao=gerar                — dispara a varredura manualmente (opcional)
// DELETE ?id=X
// ============================================================
require_once __DIR__ . '/../../backend/banco/conexao.php';
require_once __DIR__ . '/../helpers/alertas_auto.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $acao = trim($_GET['acao'] ?? 'listar');

    if ($acao === 'listar') {
        $nivel = trim($_GET['nivel'] ?? '');
        $lido  = $_GET['lido']  ?? '';
        $tipo  = trim($_GET['tipo']  ?? '');

        $where  = [];
        $params = [];

        if ($nivel) { $where[] = 'nivel=?';  $params[] = $nivel; }
        if ($tipo)  { $where[] = 'tipo=?';   $params[] = $tipo;  }
        if ($lido !== '') { $where[] = 'lido=?'; $params[] = (int)$lido; }

        $sql  = "SELECT * FROM `{$p}alertas`"
              . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
              . " ORDER BY lido ASC, nivel DESC, criado_em DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll();

        $kpi = [
            'total'    => count($dados),
            'nao_lido' => (int) $pdo->query("SELECT COUNT(*) FROM `{$p}alertas` WHERE lido=0")->fetchColumn(),
            'urgente'  => (int) $pdo->query("SELECT COUNT(*) FROM `{$p}alertas` WHERE nivel='urgente' AND lido=0")->fetchColumn(),
            'aviso'    => (int) $pdo->query("SELECT COUNT(*) FROM `{$p}alertas` WHERE nivel='aviso'   AND lido=0")->fetchColumn(),
            'info'     => (int) $pdo->query("SELECT COUNT(*) FROM `{$p}alertas` WHERE nivel='info'    AND lido=0")->fetchColumn(),
        ];
        respostaJSON(['success' => true, 'dados' => $dados, 'kpi' => $kpi]);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    // ── Salvar (criar / editar) ────────────────────────────────
    if ($acao === 'salvar') {
        $id      = (int)($d['id'] ?? 0);
        $titulo  = trim($d['titulo']  ?? '');
        if (!$titulo) respostaJSON(['success' => false, 'erro' => 'Título é obrigatório.']);

        $tipos_ok  = ['vencimento','limite_cartao','orcamento','meta','emprestimo','saldo','custom'];
        $niveis_ok = ['info','aviso','urgente'];
        $tipo      = in_array($d['tipo']  ?? '', $tipos_ok)  ? $d['tipo']  : 'custom';
        $nivel     = in_array($d['nivel'] ?? '', $niveis_ok) ? $d['nivel'] : 'aviso';
        $mensagem  = trim($d['mensagem']   ?? '');
        $data_al   = !empty($d['data_alerta']) ? $d['data_alerta'] : null;

        if ($id > 0) {
            $pdo->prepare(
                "UPDATE `{$p}alertas` SET tipo=?, titulo=?, mensagem=?, nivel=?, data_alerta=? WHERE id=?"
            )->execute([$tipo, $titulo, $mensagem, $nivel, $data_al, $id]);
            respostaJSON(['success' => true, 'msg' => 'Alerta atualizado.']);
        }

        $pdo->prepare(
            "INSERT INTO `{$p}alertas` (tipo, titulo, mensagem, nivel, data_alerta) VALUES (?,?,?,?,?)"
        )->execute([$tipo, $titulo, $mensagem, $nivel, $data_al]);
        respostaJSON(['success' => true, 'msg' => 'Alerta criado.']);
    }

    // ── Marcar como lido ──────────────────────────────────────
    if ($acao === 'marcar_lido') {
        $id = (int)($d['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE `{$p}alertas` SET lido=1 WHERE id=?")->execute([$id]);
        } else {
            $pdo->exec("UPDATE `{$p}alertas` SET lido=1 WHERE lido=0");
        }
        respostaJSON(['success' => true, 'msg' => 'Marcado(s) como lido(s).']);
    }

    // ── Gerar alertas automáticos ─────────────────────────────
    if ($acao === 'gerar') {
        $gerados = gerarAlertasAuto($pdo, $p);
        respostaJSON(['success' => true, 'msg' => "$gerados alerta(s) gerado(s).", 'gerados' => $gerados]);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);
    $pdo->prepare("DELETE FROM `{$p}alertas` WHERE id=?")->execute([$id]);
    respostaJSON(['success' => true, 'msg' => 'Alerta removido.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
