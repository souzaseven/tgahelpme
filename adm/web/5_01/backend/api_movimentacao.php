<?php
require_once __DIR__ . '/conexao.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!validateCSRF()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF inválido']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

try {

    /* =====================================================
       RELATÓRIO DE MOVIMENTAÇÃO MENSAL DE USUÁRIOS
    ===================================================== */
    if ($action === 'movimentacao_mensal') {

        $mes = trim($body['mes'] ?? date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = date('Y-m');
        }

        [$ano, $m] = explode('-', $mes);
        $ano = (int)$ano;
        $m   = (int)$m;

        if ($ano < 2000 || $ano > 2099 || $m < 1 || $m > 12) {
            throw new Exception('Mês inválido');
        }

        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.acao,
                a.registro_id,
                a.dados_antes,
                a.dados_depois,
                DATE_FORMAT(a.criado_em, '%d/%m/%Y') AS data_fmt
            FROM auditoria a
            WHERE a.modulo = 'Usuários Web'
              AND YEAR(a.criado_em)  = ?
              AND MONTH(a.criado_em) = ?
            ORDER BY a.criado_em ASC
        ");
        $stmt->execute([$ano, $m]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $criados     = [];
        $desativados = [];

        foreach ($rows as $r) {
            $dd = $r['dados_depois'] ? (json_decode($r['dados_depois'], true) ?: []) : [];
            $da = $r['dados_antes']  ? (json_decode($r['dados_antes'],  true) ?: []) : [];

            $nome      = $dd['nome_empresa']   ?? $da['nome_empresa']   ?? "ID {$r['registro_id']}";
            $codigo    = strtoupper($dd['codigo_empresa'] ?? $da['codigo_empresa'] ?? '');
            $qtdDepois = isset($dd['qtd_usuarios']) ? (int)$dd['qtd_usuarios'] : 0;
            $qtdAntes  = isset($da['qtd_usuarios'])  ? (int)$da['qtd_usuarios']  : 0;
            $stAntes   = strtoupper($da['status']  ?? '');
            $stDepois  = strtoupper($dd['status'] ?? '');
            $data      = $r['data_fmt'];

            /* CREATE — todos os usuários são novos */
            if ($r['acao'] === 'CREATE' && $qtdDepois > 0) {
                $users = [];
                for ($i = 1; $i <= $qtdDepois; $i++) {
                    $users[] = [
                        'username'     => "{$codigo}.USER{$i}",
                        'nome'         => $nome,
                        'data_cria'    => $data,
                        'data_desativ' => '-',
                    ];
                }
                $criados[] = ['empresa' => $nome, 'codigo' => $codigo, 'qtd' => $qtdDepois, 'usuarios' => $users];
            }

            elseif ($r['acao'] === 'UPDATE') {

                $diff        = $qtdDepois - $qtdAntes;
                $desativou   = $stAntes === 'ATIVO'   && $stDepois === 'INATIVO';
                $reativou    = $stAntes === 'INATIVO'  && $stDepois === 'ATIVO';

                /* Usuários adicionados */
                if ($diff > 0 && !$desativou) {
                    $users = [];
                    for ($i = $qtdAntes + 1; $i <= $qtdDepois; $i++) {
                        $users[] = ['username' => "{$codigo}.USER{$i}", 'nome' => $nome, 'data_cria' => $data, 'data_desativ' => '-'];
                    }
                    $criados[] = ['empresa' => $nome, 'codigo' => $codigo, 'qtd' => $diff, 'usuarios' => $users];
                }

                /* Empresa reativada */
                if ($reativou && $qtdDepois > 0) {
                    $users = [];
                    for ($i = 1; $i <= $qtdDepois; $i++) {
                        $users[] = ['username' => "{$codigo}.USER{$i}", 'nome' => $nome, 'data_cria' => '-', 'data_desativ' => '-'];
                    }
                    $criados[] = ['empresa' => $nome, 'codigo' => $codigo, 'qtd' => $qtdDepois, 'usuarios' => $users, 'note' => 'Reativação'];
                }

                /* Empresa desativada (status ATIVO→INATIVO) */
                if ($desativou && $qtdAntes > 0) {
                    $users = [];
                    for ($i = 1; $i <= $qtdAntes; $i++) {
                        $users[] = ['username' => "{$codigo}.USER{$i}", 'nome' => $nome, 'data_cria' => '-', 'data_desativ' => $data];
                    }
                    $desativados[] = ['empresa' => $nome, 'codigo' => $codigo, 'qtd' => $qtdAntes, 'usuarios' => $users];
                }

                /* Usuários removidos (qtd diminuiu) */
                if ($diff < 0 && !$desativou) {
                    $users = [];
                    for ($i = $qtdDepois + 1; $i <= $qtdAntes; $i++) {
                        $users[] = ['username' => "{$codigo}.USER{$i}", 'nome' => $nome, 'data_cria' => '-', 'data_desativ' => $data];
                    }
                    $desativados[] = ['empresa' => $nome, 'codigo' => $codigo, 'qtd' => abs($diff), 'usuarios' => $users];
                }
            }

            /* DELETE — todos os usuários removidos */
            elseif ($r['acao'] === 'DELETE' && $qtdAntes > 0) {
                $users = [];
                for ($i = 1; $i <= $qtdAntes; $i++) {
                    $users[] = ['username' => "{$codigo}.USER{$i}", 'nome' => $nome, 'data_cria' => '-', 'data_desativ' => $data];
                }
                $desativados[] = ['empresa' => $nome, 'codigo' => $codigo, 'qtd' => $qtdAntes, 'usuarios' => $users];
            }
        }

        echo json_encode([
            'success'           => true,
            'periodo'           => $mes,
            'criados'           => $criados,
            'desativados'       => $desativados,
            'total_criados'     => array_sum(array_column($criados,     'qtd')),
            'total_desativados' => array_sum(array_column($desativados, 'qtd')),
        ]);
        exit;
    }

    throw new Exception('Ação inválida');

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
