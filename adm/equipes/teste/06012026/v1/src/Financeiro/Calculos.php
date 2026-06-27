<?php

namespace FinanceOS\Financeiro;

class Calculos
{
    /**
     * Calcula o mês/ano de PAGAMENTO da fatura (padrão brasileiro).
     * Compra dia 3, fechamento dia 10 → fecha em junho → paga em julho → retorna [7, 2026]
     *
     * @return array{0: int, 1: int} [mes_pagamento, ano_pagamento]
     */
    public static function mesFatura(string $dataCompra, int $diaFechamento): array
    {
        [$a, $m, $d] = array_map('intval', explode('-', $dataCompra));

        // Mês de fechamento
        $mClose = $d <= $diaFechamento ? $m : ($m === 12 ? 1 : $m + 1);
        $aClose = $d <= $diaFechamento ? $a : ($m === 12 ? $a + 1 : $a);

        // Mês de pagamento = fechamento + 1
        $mF = $mClose === 12 ? 1 : $mClose + 1;
        $aF = $mClose === 12 ? $aClose + 1 : $aClose;

        return [$mF, $aF];
    }

    /**
     * Divide um valor entre N responsáveis, ajustando centavos no primeiro.
     *
     * @return float[] Array com N valores que somam exatamente $valorTotal
     */
    public static function dividirValor(float $valorTotal, int $n): array
    {
        if ($n <= 0) return [];
        $vlUnit  = round($valorTotal / $n, 2);
        $vlResto = round($valorTotal - ($vlUnit * $n), 2);
        $partes  = array_fill(0, $n, $vlUnit);
        $partes[0] = round($partes[0] + $vlResto, 2);
        return $partes;
    }

    /**
     * Determina se um pagamento de empréstimo quita o saldo total.
     */
    public static function quitaTotal(float $valorPago, float $saldoDevedor, float $tolerancia = 0.01): bool
    {
        return $valorPago >= ($saldoDevedor - $tolerancia);
    }

    /**
     * Calcula data da N-ésima parcela a partir da data inicial.
     */
    public static function dataParcela(string $dataInicio, int $parcela): string
    {
        if ($parcela <= 1) return $dataInicio;
        $ts = strtotime($dataInicio . ' +' . ($parcela - 1) . ' months');
        return date('Y-m-d', $ts);
    }
}
