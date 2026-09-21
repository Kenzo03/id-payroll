<?php

namespace Tests\Unit;

use App\Payroll\PayrollCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PayrollCalculatorTest extends TestCase
{
    private PayrollCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new PayrollCalculator(require __DIR__.'/../../config/payroll.php');
    }

    public function test_monthly_ter_withholding_for_category_a(): void
    {
        $m = $this->calc->month(10_000_000, 0, 'TK/0');

        // Taxable gross = salary + employer BPJS Kes 4% + JKK 0.24% + JKM 0.3%.
        $this->assertSame(10_454_000, $m['gross']);
        $this->assertSame('A', $m['ter_category']);
        $this->assertSame(250, $m['ter_rate']);
        $this->assertSame(261_350, $m['pph21']);
        // 10,000,000 - Kes 100,000 - JHT 200,000 - JP 100,000 - PPh 261,350
        $this->assertSame(9_338_650, $m['take_home']);
    }

    public function test_december_trues_up_to_annual_pasal_17_tax(): void
    {
        $y = $this->calc->year(10_000_000, 0, 'TK/0');

        // 125,448,000 - biaya jabatan 6,000,000 (capped) - JHT/JP 3,600,000 - PTKP 54,000,000
        $this->assertSame(6_000_000, $y['annual']['biaya_jabatan']);
        $this->assertSame(61_848_000, $y['annual']['pkp']);
        // 5% x 60,000,000 + 15% x 1,848,000
        $this->assertSame(3_277_200, $y['annual']['pph21']);
        $this->assertSame(3_277_200 - 261_350 * 11, $y['months'][12]['pph21']);
        $this->assertSame(
            $y['annual']['pph21'],
            array_sum(array_column($y['months'], 'pph21')),
        );
    }

    public function test_bpjs_caps_and_category_b(): void
    {
        $m = $this->calc->month(20_000_000, 0, 'K/1');

        $this->assertSame(120_000, $m['bpjs_employee']['kesehatan']); // 1% of 12,000,000 cap
        $this->assertSame(480_000, $m['bpjs_employer']['kesehatan']);
        $this->assertSame(110_863, $m['bpjs_employee']['jp']);        // 1% of 11,086,300 cap
        $this->assertSame(400_000, $m['bpjs_employee']['jht']);       // JHT has no cap
        $this->assertSame('B', $m['ter_category']);
        $this->assertSame(800, $m['ter_rate']);
        $this->assertSame(1_647_040, $m['pph21']);
    }

    public function test_variable_allowance_is_taxed_but_not_bpjs_base(): void
    {
        $m = $this->calc->month(8_000_000, 1_000_000, 'K/0', 1_500_000);

        // BPJS base is 9,000,000 (salary + fixed allowance), not 10,500,000.
        $this->assertSame(90_000, $m['bpjs_employee']['kesehatan']);
        $this->assertSame(180_000, $m['bpjs_employee']['jht']);
        $this->assertSame(90_000, $m['bpjs_employee']['jp']);
        // 9,000,000 + 1,500,000 + Kes 360,000 + JKK 21,600 + JKM 27,000
        $this->assertSame(10_908_600, $m['gross']);
        $this->assertSame(300, $m['ter_rate']);
        $this->assertSame(327_258, $m['pph21']);
        $this->assertSame(10_500_000 - 360_000 - 327_258, $m['take_home']);
    }

    public function test_bpjs_tk_on_base_salary_only(): void
    {
        $m = $this->calc->month(8_000_000, 1_000_000, 'K/0', 0, tkBaseSalaryOnly: true);

        $this->assertSame(90_000, $m['bpjs_employee']['kesehatan']);  // still salary + fixed allowance
        $this->assertSame(160_000, $m['bpjs_employee']['jht']);       // 2% of 8,000,000
        $this->assertSame(80_000, $m['bpjs_employee']['jp']);
        $this->assertSame(19_200, $m['bpjs_employer']['jkk']);
        $this->assertSame(24_000, $m['bpjs_employer']['jkm']);
        // 9,000,000 + Kes 360,000 + JKK 19,200 + JKM 24,000
        $this->assertSame(9_403_200, $m['gross']);
        $this->assertSame(175, $m['ter_rate']);
        $this->assertSame(164_556, $m['pph21']);
    }

    public function test_without_jp(): void
    {
        $y = $this->calc->year(8_000_000, 1_000_000, 'K/0', withJp: false);
        $m = $y['months'][1];

        $this->assertSame(0, $m['bpjs_employee']['jp']);
        $this->assertSame(0, $m['bpjs_employer']['jp']);
        $this->assertSame(9_408_600, $m['gross']);
        $this->assertSame(164_650, $m['pph21']);
        $this->assertSame(9_000_000 - 90_000 - 180_000 - 164_650, $m['take_home']);
        // Only JHT is deducted in the annual calculation.
        $this->assertSame(180_000 * 12, $y['annual']['pension_contributions']);
    }

    public function test_income_below_ptkp_pays_no_tax(): void
    {
        $y = $this->calc->year(4_000_000, 0, 'TK/0');

        $this->assertSame(0, $y['annual']['pph21']);
        $this->assertSame(0, $y['months'][1]['pph21']);
        $this->assertSame(0, $y['months'][12]['pph21']);
    }

    public function test_ptkp_amounts(): void
    {
        $this->assertSame(54_000_000, $this->calc->ptkp('TK/0'));
        $this->assertSame(63_000_000, $this->calc->ptkp('K/1'));
        $this->assertSame(72_000_000, $this->calc->ptkp('K/3'));
    }

    public function test_unknown_ptkp_status_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calc->month(10_000_000, 0, 'X/9');
    }
}
