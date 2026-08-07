<?php
// ============================================================
// recorrencias_auto.php — Geração automática dos lançamentos de
// "Contas fixas" (recorrências). Antes, cada mês precisava de um
// clique manual em "Gerar" (ver pages/recorrencias.php); agora
// gerarRecorrenciasAuto() é chamada em todo carregamento de página
// (ver index.php) e cria os lançamentos pendentes do mês atual e do
// próximo automaticamente — o dedup dentro de gerarParaMes() evita
// duplicar caso já tenham sido gerados (manualmente ou não).
// ============================================================
require_once __DIR__ . '/../../src/Financeiro/Calculos.php';

use FinanceOS\Financeiro\Calculos;

if (!function_exists('gerarParaMes')) {
    function gerarParaMes(PDO $pdo, string $p, array $rec, int $mes, int $ano): bool
    {
        if (!Calculos::recorrenciaDeveGerarNoMes($rec, $mes, $ano)) return false;

        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$p}transacoes`
             WHERE recorrencia_id=? AND MONTH(data)=? AND YEAR(data)=?"
        );
        $check->execute([$rec['id'], $mes, $ano]);
        if ((int)$check->fetchColumn() > 0) return false;

        $diasNoMes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
        $dia  = min((int)$rec['dia_vencimento'], $diasNoMes);
        $data = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);

        $pdo->prepare(
            "INSERT INTO `{$p}transacoes`
             (tipo, descricao, valor, data, categoria_id, conta_id, cartao_id, recorrencia_id, status)
             VALUES (?,?,?,?,?,?,?,?,'pendente')"
        )->execute([
            $rec['tipo'], $rec['descricao'], $rec['valor'], $data,
            $rec['categoria_id'], $rec['conta_id'], $rec['cartao_id'], $rec['id'],
        ]);
        return true;
    }
}

if (!function_exists('gerarRecorrenciasAuto')) {
    function gerarRecorrenciasAuto(PDO $pdo, string $p): int
    {
        $recs = $pdo->query("SELECT * FROM `{$p}recorrencias` WHERE ativo=1")->fetchAll();
        if (!$recs) return 0;

        $mesCurr = (int)date('m');
        $anoCurr = (int)date('Y');
        $mesNext = $mesCurr === 12 ? 1 : $mesCurr + 1;
        $anoNext = $mesCurr === 12 ? $anoCurr + 1 : $anoCurr;

        $gerados = 0;
        foreach ($recs as $rec) {
            if (gerarParaMes($pdo, $p, $rec, $mesCurr, $anoCurr)) $gerados++;
            if (gerarParaMes($pdo, $p, $rec, $mesNext, $anoNext)) $gerados++;
        }
        return $gerados;
    }
}
