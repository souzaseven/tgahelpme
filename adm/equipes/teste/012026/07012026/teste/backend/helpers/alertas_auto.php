<?php

/**
 * Varre faturas de cartão, parcelas de empréstimo e metas com prazo
 * próximo e cria um alerta na tabela `alertas` pra cada situação
 * encontrada — sem duplicar se já existir um alerta não lido criado
 * hoje pra aquela mesma referência (referencia_tipo + referencia_id).
 *
 * Chamada em todo carregamento de página (ver index.php, antes de
 * contar os alertas não lidos pro sino do topo) e também sob demanda
 * pelo botão "Verificar agora" da tela de Alertas — por isso é
 * seguro chamar com frequência, o dedup por dia evita repetir.
 */
function gerarAlertasAuto(PDO $pdo, string $p): int
{
    $hoje    = new DateTime();
    $gerados = 0;

    // Faturas de cartão — vencendo em até 7 dias
    $cartoes = $pdo->query("SELECT * FROM `{$p}cartoes` WHERE ativo=1")->fetchAll();
    foreach ($cartoes as $cc) {
        $dia = (int)$cc['dia_vencimento'];
        if (!$dia) continue;

        $dtVenc = DateTime::createFromFormat(
            'Y-m-d',
            sprintf('%04d-%02d-%02d', (int)$hoje->format('Y'), (int)$hoje->format('m'), $dia)
        );
        if (!$dtVenc) continue;
        if ($dtVenc < $hoje) $dtVenc->modify('+1 month');

        $diff = (int)$hoje->diff($dtVenc)->days;
        if ($diff > 7) continue;

        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$p}alertas`
             WHERE referencia_tipo='cartao' AND referencia_id=?
               AND DATE(criado_em)=CURDATE() AND lido=0"
        );
        $check->execute([$cc['id']]);
        if ((int)$check->fetchColumn() > 0) continue;

        $nivel  = $diff <= 3 ? 'urgente' : 'aviso';
        $titulo = "Fatura {$cc['nome']} vence em {$diff} dia" . ($diff === 1 ? '' : 's');
        $pdo->prepare(
            "INSERT INTO `{$p}alertas`
             (tipo, titulo, mensagem, nivel, referencia_tipo, referencia_id, data_alerta)
             VALUES (?,?,?,?,?,?,?)"
        )->execute([
            'vencimento', $titulo,
            'Limite usado: R$ ' . number_format((float)$cc['limite_usado'], 2, ',', '.'),
            $nivel, 'cartao', $cc['id'], $dtVenc->format('Y-m-d'),
        ]);
        $gerados++;
    }

    // Parcelas de empréstimo — vencendo em até 7 dias
    try {
        $parcelas = $pdo->query(
            "SELECT ep.*, e.descricao AS emp_nome
             FROM `{$p}emprestimos_parcelas` ep
             JOIN `{$p}emprestimos` e ON e.id = ep.emprestimo_id
             WHERE ep.status='pendente'
               AND ep.data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
        )->fetchAll();
        foreach ($parcelas as $parc) {
            $dtVenc = new DateTime($parc['data_vencimento']);
            $diff   = (int)$hoje->diff($dtVenc)->days;

            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM `{$p}alertas`
                 WHERE referencia_tipo='parcela' AND referencia_id=?
                   AND DATE(criado_em)=CURDATE() AND lido=0"
            );
            $check->execute([$parc['id']]);
            if ((int)$check->fetchColumn() > 0) continue;

            $nivel  = $diff <= 3 ? 'urgente' : 'aviso';
            $titulo = "Parcela de \"{$parc['emp_nome']}\" vence em {$diff} dia" . ($diff === 1 ? '' : 's');
            $pdo->prepare(
                "INSERT INTO `{$p}alertas`
                 (tipo, titulo, mensagem, nivel, referencia_tipo, referencia_id, data_alerta)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([
                'emprestimo', $titulo,
                'R$ ' . number_format((float)$parc['valor'], 2, ',', '.'),
                $nivel, 'parcela', $parc['id'], $parc['data_vencimento'],
            ]);
            $gerados++;
        }
    } catch (PDOException $e) { /* tabela pode não existir ainda */ }

    // Metas com prazo em 30 dias e progresso < 80%
    try {
        $metas = $pdo->query(
            "SELECT * FROM `{$p}metas`
             WHERE status='ativa'
               AND data_prazo BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
        )->fetchAll();
        foreach ($metas as $meta) {
            $pct = $meta['valor_alvo'] > 0
                ? ($meta['valor_atual'] / $meta['valor_alvo']) * 100
                : 100;
            if ($pct >= 80) continue;

            $dtVenc = new DateTime($meta['data_prazo']);
            $diff   = (int)$hoje->diff($dtVenc)->days;

            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM `{$p}alertas`
                 WHERE referencia_tipo='meta' AND referencia_id=?
                   AND DATE(criado_em)=CURDATE() AND lido=0"
            );
            $check->execute([$meta['id']]);
            if ((int)$check->fetchColumn() > 0) continue;

            $nivel  = $diff <= 7 ? 'urgente' : 'aviso';
            $titulo = "Meta \"{$meta['nome']}\" vence em {$diff} dia" . ($diff === 1 ? '' : 's');
            $pdo->prepare(
                "INSERT INTO `{$p}alertas`
                 (tipo, titulo, mensagem, nivel, referencia_tipo, referencia_id, data_alerta)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([
                'meta', $titulo,
                number_format($pct, 1, ',', '.') . '% concluído — meta: R$ ' . number_format((float)$meta['valor_alvo'], 2, ',', '.'),
                $nivel, 'meta', $meta['id'], $meta['data_prazo'],
            ]);
            $gerados++;
        }
    } catch (PDOException $e) { /* tabela pode não existir ainda */ }

    return $gerados;
}
