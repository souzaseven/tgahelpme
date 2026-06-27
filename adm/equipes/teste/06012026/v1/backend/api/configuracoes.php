<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($metodo === 'GET') {
    $acao = trim($_GET['acao'] ?? 'configs');

    // ── Todas as configurações ─────────────────────────────────
    if ($acao === 'configs') {
        $rows = $pdo->query("SELECT chave, valor FROM `{$p}configuracoes`")->fetchAll();
        $cfg  = [];
        foreach ($rows as $r) $cfg[$r['chave']] = $r['valor'];
        respostaJSON(['success' => true, 'configs' => $cfg]);
    }

    // ── Categorias ────────────────────────────────────────────
    if ($acao === 'categorias') {
        $status = trim($_GET['status'] ?? 'todos');
        $where  = $status === 'ativo' ? 'WHERE ativo=1' : ($status === 'inativo' ? 'WHERE ativo=0' : '');
        $rows   = $pdo->query(
            "SELECT * FROM `{$p}categorias` $where ORDER BY tipo, nome"
        )->fetchAll();
        respostaJSON(['success' => true, 'categorias' => $rows]);
    }

    // ── Estatísticas do sistema ────────────────────────────────
    if ($acao === 'stats') {
        $tabelas = [
            'transacoes', 'categorias', 'contas', 'cartoes',
            'emprestimos', 'investimentos', 'metas', 'recorrencias', 'alertas',
        ];
        $counts = [];
        foreach ($tabelas as $t) {
            $counts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM `{$p}{$t}`")->fetchColumn();
        }

        $totalTx = (float) $pdo->query(
            "SELECT COALESCE(SUM(valor),0) FROM `{$p}transacoes` WHERE status='pago'"
        )->fetchColumn();

        $primeiraData = $pdo->query(
            "SELECT MIN(data) FROM `{$p}transacoes`"
        )->fetchColumn();

        respostaJSON([
            'success'       => true,
            'counts'        => $counts,
            'total_moviment'=> $totalTx,
            'desde'         => $primeiraData,
            'versao'        => APP_VERSION,
        ]);
    }

    // ── Exportar dados ────────────────────────────────────────
    if ($acao === 'exportar') {
        $tipo   = trim($_GET['tipo']    ?? 'transacoes');
        $deMes  = (int)($_GET['de_mes'] ?? 1);
        $deAno  = (int)($_GET['de_ano'] ?? date('Y'));
        $ateMes = (int)($_GET['ate_mes']?? (int)date('m'));
        $ateAno = (int)($_GET['ate_ano']?? date('Y'));
        $txTipo = trim($_GET['tx_tipo'] ?? '');

        $deData  = sprintf('%04d-%02d-01', $deAno, $deMes);
        $ateData = date('Y-m-t', mktime(0, 0, 0, $ateMes, 1, $ateAno));

        if ($tipo === 'transacoes') {
            $where  = ["t.data BETWEEN ? AND ?"];
            $params = [$deData, $ateData];
            if ($txTipo) { $where[] = 't.tipo = ?'; $params[] = $txTipo; }

            $stmt = $pdo->prepare(
                "SELECT t.id, t.tipo, t.descricao, t.valor, t.data, t.status,
                        t.parcela_atual, t.parcela_total, t.observacao,
                        c.nome categoria, ct.nome conta, cc.nome cartao
                 FROM `{$p}transacoes` t
                 LEFT JOIN `{$p}categorias` c  ON c.id = t.categoria_id
                 LEFT JOIN `{$p}contas`     ct ON ct.id = t.conta_id
                 LEFT JOIN `{$p}cartoes`    cc ON cc.id = t.cartao_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY t.data DESC, t.id DESC"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            respostaJSON([
                'success'   => true,
                'dados'     => $rows,
                'total'     => count($rows),
                'periodo'   => ['de' => $deData, 'ate' => $ateData],
            ]);
        }

        if ($tipo === 'categorias') {
            $rows = $pdo->query("SELECT * FROM `{$p}categorias` ORDER BY tipo, nome")->fetchAll();
            respostaJSON(['success' => true, 'dados' => $rows]);
        }

        respostaJSON(['success' => false, 'erro' => 'Tipo de exportação não reconhecido.']);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── POST ──────────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $acao = trim($d['acao'] ?? '');

    $chavesPermitidas = [
        'usuario_nome', 'usuario_email', 'moeda', 'dia_fechamento_padrao',
        'meta_poupanca', 'alerta_cartao_pct', 'alerta_dias_antecedencia',
    ];

    // ── Salvar configuração ────────────────────────────────────
    if ($acao === 'salvar_config') {
        $chave = trim($d['chave'] ?? '');
        $valor = $d['valor'] ?? '';
        if (!$chave || !in_array($chave, $chavesPermitidas)) {
            respostaJSON(['success' => false, 'erro' => 'Chave inválida.']);
        }

        $pdo->prepare(
            "INSERT INTO `{$p}configuracoes` (chave, valor)
             VALUES (?,?)
             ON DUPLICATE KEY UPDATE valor=?, atualizado_em=NOW()"
        )->execute([$chave, $valor, $valor]);
        respostaJSON(['success' => true, 'msg' => 'Configuração salva.']);
    }

    // ── Salvar lote de configurações ───────────────────────────
    if ($acao === 'salvar_configs') {
        $configs = $d['configs'] ?? [];
        if (!is_array($configs)) respostaJSON(['success' => false, 'erro' => 'Dados inválidos.']);

        $stmt = $pdo->prepare(
            "INSERT INTO `{$p}configuracoes` (chave, valor)
             VALUES (?,?)
             ON DUPLICATE KEY UPDATE valor=?, atualizado_em=NOW()"
        );
        foreach ($configs as $chave => $valor) {
            $chave = trim($chave);
            if (!in_array($chave, $chavesPermitidas)) continue;
            $stmt->execute([$chave, $valor, $valor]);
        }
        respostaJSON(['success' => true, 'msg' => 'Configurações salvas com sucesso.']);
    }

    // ── Salvar categoria ───────────────────────────────────────
    if ($acao === 'salvar_categoria') {
        $id     = (int)($d['id']            ?? 0);
        $nome   = trim($d['nome']           ?? '');
        $catPai = (int)($d['categoria_pai'] ?? 0) ?: null;
        if (!$nome) respostaJSON(['success' => false, 'erro' => 'Nome é obrigatório.']);

        // Subcategoria não pode ser pai de si mesma
        if ($catPai && $catPai === $id) $catPai = null;

        $tipos   = ['receita','despesa','ambos'];
        $tipo    = in_array($d['tipo'] ?? '', $tipos) ? $d['tipo'] : 'despesa';
        $icone   = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($d['icone'] ?? ''))) ?: 'tag';
        $cor     = preg_match('/^#[0-9a-fA-F]{6}$/', $d['cor'] ?? '') ? $d['cor'] : '#6366f1';
        $tiposIR = ['tributavel','isento','deducao_saude','deducao_educacao','deducao_previd','deducao_outro'];
        $tipoIR  = in_array($d['tipo_ir'] ?? '', $tiposIR) ? $d['tipo_ir'] : null;

        if ($id > 0) {
            $pdo->prepare(
                "UPDATE `{$p}categorias` SET nome=?, tipo=?, icone=?, cor=?, tipo_ir=?, categoria_pai=? WHERE id=?"
            )->execute([$nome, $tipo, $icone, $cor, $tipoIR, $catPai, $id]);
            respostaJSON(['success' => true, 'msg' => 'Categoria atualizada.']);
        }

        $pdo->prepare(
            "INSERT INTO `{$p}categorias` (nome, tipo, icone, cor, tipo_ir, categoria_pai) VALUES (?,?,?,?,?,?)"
        )->execute([$nome, $tipo, $icone, $cor, $tipoIR, $catPai]);
        respostaJSON(['success' => true, 'msg' => 'Categoria criada com sucesso.']);
    }

    // ── Inserir categorias sugeridas (ignora as já existentes) ────
    if ($acao === 'inserir_padrao') {
        $sugeridas = [
            // Despesas
            ['Supermercado',        'despesa', 'cart-shopping',       '#f97316'],
            ['Farmácia',            'despesa', 'prescription-bottle', '#ec4899'],
            ['Médico / Consultas',  'despesa', 'stethoscope',         '#f43f5e'],
            ['Combustível',         'despesa', 'gas-pump',            '#eab308'],
            ['Contas de Casa',      'despesa', 'house',               '#6366f1'],
            ['Internet / Telefone', 'despesa', 'wifi',                '#06b6d4'],
            ['Academia',            'despesa', 'dumbbell',            '#10b981'],
            ['Restaurante',         'despesa', 'utensils',            '#f97316'],
            ['Delivery',            'despesa', 'motorcycle',          '#f59e0b'],
            ['Pets',                'despesa', 'paw',                 '#f59e0b'],
            ['Beleza / Higiene',    'despesa', 'scissors',            '#ec4899'],
            ['Presentes',           'despesa', 'gift',                '#8b5cf6'],
            ['Impostos / Taxas',    'despesa', 'file-invoice-dollar', '#ef4444'],
            ['Seguros',             'despesa', 'shield-halved',       '#3b82f6'],
            ['Manutenção',          'despesa', 'wrench',              '#f59e0b'],
            ['Viagens',             'despesa', 'plane',               '#06b6d4'],
            ['Streaming',           'despesa', 'tv',                  '#8b5cf6'],
            ['Doações',             'despesa', 'hand-holding-heart',  '#10b981'],
            ['Livros / Cursos',     'despesa', 'book',                '#8b5cf6'],
            ['Eletrônicos',         'despesa', 'laptop',              '#6366f1'],
            // Receitas
            ['13º / Bônus',         'receita', 'money-bill-wave',     '#10b981'],
            ['Freelance / PJ',      'receita', 'laptop',              '#8b5cf6'],
            ['Venda de Produtos',   'receita', 'box',                 '#f59e0b'],
            ['Dividendos',          'receita', 'chart-line',          '#06b6d4'],
            ['Pensão / Benefício',  'receita', 'hand-holding-dollar', '#06b6d4'],
            ['Cashback',            'receita', 'rotate-left',         '#10b981'],
        ];

        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$p}categorias` WHERE nome=? AND tipo=?"
        );
        $ins = $pdo->prepare(
            "INSERT INTO `{$p}categorias` (nome, tipo, icone, cor) VALUES (?,?,?,?)"
        );

        $inseridas = 0;
        foreach ($sugeridas as [$nome, $tipo, $icone, $cor]) {
            $check->execute([$nome, $tipo]);
            if ((int)$check->fetchColumn() === 0) {
                $ins->execute([$nome, $tipo, $icone, $cor]);
                $inseridas++;
            }
        }

        respostaJSON([
            'success'   => true,
            'msg'       => $inseridas > 0
                ? "$inseridas categorias adicionadas com sucesso!"
                : 'Todas as categorias sugeridas já existem.',
            'inseridas' => $inseridas,
        ]);
    }

    // ── Toggle ativo da categoria ──────────────────────────────
    if ($acao === 'toggle_categoria') {
        $id = (int)($d['id'] ?? 0);
        if (!$id) respostaJSON(['success' => false, 'erro' => 'ID inválido.']);
        $pdo->prepare(
            "UPDATE `{$p}categorias` SET ativo = 1 - ativo WHERE id=?"
        )->execute([$id]);
        respostaJSON(['success' => true, 'msg' => 'Status alterado.']);
    }

    respostaJSON(['success' => false, 'erro' => 'Ação não reconhecida.']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $tipo = trim($_GET['tipo'] ?? '');
    $id   = (int)($_GET['id']  ?? 0);

    if ($tipo === 'categoria' && $id) {
        // Verifica se está em uso
        $stmtUso = $pdo->prepare("SELECT COUNT(*) FROM `{$p}transacoes` WHERE categoria_id=?");
        $stmtUso->execute([$id]);
        $uso = (int) $stmtUso->fetchColumn();

        if ($uso > 0) {
            $pdo->prepare("UPDATE `{$p}categorias` SET ativo=0 WHERE id=?")->execute([$id]);
            respostaJSON(['success' => true, 'msg' => "Categoria desativada ($uso transações vinculadas mantidas)."]);
        } else {
            $pdo->prepare("DELETE FROM `{$p}categorias` WHERE id=?")->execute([$id]);
            respostaJSON(['success' => true, 'msg' => 'Categoria removida.']);
        }
    }

    respostaJSON(['success' => false, 'erro' => 'Parâmetros inválidos.']);
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
