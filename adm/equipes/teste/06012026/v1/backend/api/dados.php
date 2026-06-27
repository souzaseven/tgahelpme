<?php
require_once __DIR__ . '/../../backend/banco/conexao.php';

$p      = TABLE_PREFIX;
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET: contagens por entidade ───────────────────────────────
if ($metodo === 'GET') {
    respostaJSON(['success' => true, 'counts' => [
        'despesas'     => (int)$pdo->query("SELECT COUNT(*) FROM `{$p}transacoes` WHERE tipo='despesa'")->fetchColumn(),
        'receitas'     => (int)$pdo->query("SELECT COUNT(*) FROM `{$p}transacoes` WHERE tipo='receita'")->fetchColumn(),
        'emprestimos'  => (int)$pdo->query("SELECT COUNT(*) FROM `{$p}emprestimos`")->fetchColumn(),
        'investimentos'=> (int)$pdo->query("SELECT COUNT(*) FROM `{$p}investimentos`")->fetchColumn(),
        'metas'        => (int)$pdo->query("SELECT COUNT(*) FROM `{$p}metas`")->fetchColumn(),
    ]]);
}

// ── DELETE: exclusão em massa ─────────────────────────────────
if ($metodo === 'DELETE') {
    $tipo = trim($_GET['tipo'] ?? '');

    $pdo->beginTransaction();
    try {
        switch ($tipo) {

            case 'despesas':
                // Restaura saldo das contas para despesas pagas
                $pdo->exec(
                    "UPDATE `{$p}contas` c
                     JOIN (
                         SELECT conta_id, SUM(valor) total
                         FROM `{$p}transacoes`
                         WHERE tipo='despesa' AND status='pago' AND conta_id IS NOT NULL
                         GROUP BY conta_id
                     ) s ON s.conta_id = c.id
                     SET c.saldo_atual = c.saldo_atual + s.total"
                );
                $pdo->exec("DELETE FROM `{$p}transacoes` WHERE tipo='despesa'");
                $msg = 'Todas as despesas foram excluídas.';
                break;

            case 'receitas':
                // Restaura saldo das contas para receitas recebidas
                $pdo->exec(
                    "UPDATE `{$p}contas` c
                     JOIN (
                         SELECT conta_id, SUM(valor) total
                         FROM `{$p}transacoes`
                         WHERE tipo='receita' AND status='pago' AND conta_id IS NOT NULL
                         GROUP BY conta_id
                     ) s ON s.conta_id = c.id
                     SET c.saldo_atual = c.saldo_atual - s.total"
                );
                $pdo->exec("DELETE FROM `{$p}transacoes` WHERE tipo='receita'");
                $msg = 'Todas as receitas foram excluídas.';
                break;

            case 'emprestimos':
                // Remove transações pendentes vinculadas às parcelas
                $pdo->exec(
                    "DELETE t FROM `{$p}transacoes` t
                     JOIN `{$p}emprestimos_parcelas` ep ON ep.transacao_id = t.id
                     WHERE t.status = 'pendente'"
                );
                $pdo->exec("DELETE FROM `{$p}emprestimos`");
                $msg = 'Todos os empréstimos foram excluídos.';
                break;

            case 'investimentos':
                $pdo->exec("DELETE FROM `{$p}investimentos`");
                $msg = 'Todos os investimentos foram excluídos.';
                break;

            case 'metas':
                $pdo->exec("DELETE FROM `{$p}metas`");
                $msg = 'Todas as metas foram excluídas.';
                break;

            case 'tudo':
                // Reseta saldos das contas para o saldo inicial
                $pdo->exec("UPDATE `{$p}contas` SET saldo_atual = saldo_inicial");
                // Remove tudo na ordem correta
                $pdo->exec("DELETE FROM `{$p}transacoes`");
                $pdo->exec("DELETE FROM `{$p}emprestimos`");
                $pdo->exec("DELETE FROM `{$p}investimentos`");
                $pdo->exec("DELETE FROM `{$p}metas`");
                $pdo->exec("DELETE FROM `{$p}alertas`");
                $pdo->exec("DELETE FROM `{$p}orcamentos`");
                $msg = 'Todos os dados foram excluídos. Saldos das contas resetados ao valor inicial.';
                break;

            default:
                respostaJSON(['success' => false, 'erro' => 'Tipo inválido.']);
        }

        $pdo->commit();
        respostaJSON(['success' => true, 'msg' => $msg]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        erroInterno($e);
    }
}

respostaJSON(['success' => false, 'erro' => 'Método não permitido.']);
