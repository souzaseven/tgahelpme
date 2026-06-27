<?php

namespace FinanceOS\Tests\Financeiro;

use FinanceOS\Financeiro\Calculos;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CalculosTest extends TestCase
{
    // ── mesFatura ─────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('providerMesFatura')]
    public function mesFaturaRetornaCorretamente(
        string $dataCompra,
        int    $diaFechamento,
        int    $mesEsperado,
        int    $anoEsperado,
        string $descricao
    ): void {
        [$mes, $ano] = Calculos::mesFatura($dataCompra, $diaFechamento);
        $this->assertSame($mesEsperado, $mes, $descricao . " — mês");
        $this->assertSame($anoEsperado, $ano, $descricao . " — ano");
    }

    public static function providerMesFatura(): array
    {
        return [
            // compra antes do fechamento → paga mês seguinte ao fechamento
            ['2026-06-03', 10, 7, 2026, 'Compra dia 3, fecha dia 10 → paga Julho'],
            ['2026-06-10', 10, 7, 2026, 'Compra no dia do fechamento → paga Julho'],
            // compra depois do fechamento → pula para o próximo ciclo
            ['2026-06-15', 10, 8, 2026, 'Compra dia 15, fecha dia 10 → paga Agosto'],
            // virada de ano
            ['2026-12-05', 10, 1, 2027, 'Compra em Dez antes do fechamento → paga Janeiro/27'],
            ['2026-12-15', 10, 2, 2027, 'Compra em Dez depois do fechamento → paga Fevereiro/27'],
            // fechamento no final do mês
            ['2026-01-30', 28, 2, 2026, 'Compra dia 30, fecha dia 28 → paga Março'],
            ['2026-01-20', 28, 2, 2026, 'Compra dia 20, fecha dia 28 → paga Fevereiro'],
        ];
    }

    // ── dividirValor ──────────────────────────────────────────────────

    #[Test]
    public function dividirValorSomaTotalExato(): void
    {
        $partes = Calculos::dividirValor(100.00, 3);
        $this->assertCount(3, $partes);
        $this->assertEqualsWithDelta(100.00, array_sum($partes), 0.001);
    }

    #[Test]
    public function dividirValorAjustaCentavosNoPrimeiro(): void
    {
        // 100 ÷ 3 = 33,33 | 33,33 | 33,34
        $partes = Calculos::dividirValor(100.00, 3);
        $this->assertEqualsWithDelta(33.34, $partes[0], 0.001);
        $this->assertEqualsWithDelta(33.33, $partes[1], 0.001);
        $this->assertEqualsWithDelta(33.33, $partes[2], 0.001);
    }

    #[Test]
    public function dividirValorEntreUmRetornaTudo(): void
    {
        $partes = Calculos::dividirValor(250.75, 1);
        $this->assertCount(1, $partes);
        $this->assertEqualsWithDelta(250.75, $partes[0], 0.001);
    }

    #[Test]
    public function dividirValorEntreZeroRetornaVazio(): void
    {
        $this->assertSame([], Calculos::dividirValor(100.00, 0));
    }

    // ── quitaTotal ────────────────────────────────────────────────────

    #[Test]
    public function quitaTotalQuandoPagaMaisQueOSaldo(): void
    {
        $this->assertTrue(Calculos::quitaTotal(1500.00, 1200.00));
    }

    #[Test]
    public function quitaTotalQuandoPagaExatamente(): void
    {
        $this->assertTrue(Calculos::quitaTotal(1200.00, 1200.00));
    }

    #[Test]
    public function quitaTotalDentroDaTolerancia(): void
    {
        // Diferença de R$0,005 → dentro da tolerância de R$0,01
        $this->assertTrue(Calculos::quitaTotal(1199.995, 1200.00));
    }

    #[Test]
    public function naoQuitaTotalQuandoPagouMenos(): void
    {
        $this->assertFalse(Calculos::quitaTotal(900.00, 1200.00));
    }

    // ── dataParcela ───────────────────────────────────────────────────

    #[Test]
    public function dataParcelaPrimeiraRetornaMesmaData(): void
    {
        $this->assertSame('2026-06-10', Calculos::dataParcela('2026-06-10', 1));
    }

    #[Test]
    public function dataParcelaSegundaIncrementaUmMes(): void
    {
        $this->assertSame('2026-07-10', Calculos::dataParcela('2026-06-10', 2));
    }

    #[Test]
    public function dataParcelaVirataDeAno(): void
    {
        $this->assertSame('2027-01-15', Calculos::dataParcela('2026-12-15', 2));
    }
}
