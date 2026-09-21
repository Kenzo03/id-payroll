<?php

namespace App\Payroll;

use InvalidArgumentException;

/**
 * Monthly Indonesian payroll for a permanent employee: BPJS, PPh 21 (TER
 * method, PMK 168/2023) and take-home pay. All amounts are integer rupiah.
 *
 * ponytail: assumes the same salary every month of the year. Mid-year joiners,
 * THR/bonus months and non-resident tax are not covered.
 */
class PayrollCalculator
{
    public function __construct(private array $rules) {}

    /**
     * Jan–Nov: withholding uses the flat TER rate on monthly gross.
     * $allowance is fixed (part of the BPJS base); $variableAllowance, e.g. transport
     * paid per attendance day, is taxable but not part of the BPJS base.
     * $tkBaseSalaryOnly: some employers compute BPJS Ketenagakerjaan (JHT, JP, JKK,
     * JKM) on base salary alone; BPJS Kesehatan still uses salary + fixed allowance.
     * $withJp = false: employee not enrolled in Jaminan Pensiun, so no JP either side.
     */
    public function month(int $salary, int $allowance, string $ptkpStatus, int $variableAllowance = 0, bool $tkBaseSalaryOnly = false, bool $withJp = true): array
    {
        $category = $this->rules['ter_category'][$ptkpStatus]
            ?? throw new InvalidArgumentException("Unknown PTKP status: {$ptkpStatus}");

        $bpjs = $this->rules['bpjs'];
        $base = $salary + $allowance;
        $tkBase = $tkBaseSalaryOnly ? $salary : $base;
        $jpBase = $withJp ? $this->capped($tkBase, $bpjs['jp']['cap']) : 0;

        $employee = [
            'kesehatan' => $this->pct($this->capped($base, $bpjs['kesehatan']['cap']), $bpjs['kesehatan']['employee']),
            'jht' => $this->pct($tkBase, $bpjs['jht']['employee']),
            'jp' => $this->pct($jpBase, $bpjs['jp']['employee']),
        ];
        $employer = [
            'kesehatan' => $this->pct($this->capped($base, $bpjs['kesehatan']['cap']), $bpjs['kesehatan']['employer']),
            'jht' => $this->pct($tkBase, $bpjs['jht']['employer']),
            'jp' => $this->pct($jpBase, $bpjs['jp']['employer']),
            'jkk' => $this->pct($tkBase, $bpjs['jkk']['employer']),
            'jkm' => $this->pct($tkBase, $bpjs['jkm']['employer']),
        ];

        // Employer-paid health, JKK and JKM count as taxable income; employer JHT/JP do not.
        $gross = $base + $variableAllowance + $employer['kesehatan'] + $employer['jkk'] + $employer['jkm'];
        $terRate = $this->bracket($this->rules['ter'][$category], $gross);
        $pph21 = $this->pct($gross, $terRate);

        return [
            'salary' => $salary,
            'allowance' => $allowance,
            'variable_allowance' => $variableAllowance,
            'bpjs_employee' => $employee,
            'bpjs_employer' => $employer,
            'gross' => $gross,
            'ter_category' => $category,
            'ter_rate' => $terRate,
            'pph21' => $pph21,
            'take_home' => $base + $variableAllowance - array_sum($employee) - $pph21,
        ];
    }

    /** Full year: December trues up to the annual Pasal 17 tax, so it can differ (or go negative). */
    public function year(int $salary, int $allowance, string $ptkpStatus, int $variableAllowance = 0, bool $tkBaseSalaryOnly = false, bool $withJp = true): array
    {
        $monthly = $this->month($salary, $allowance, $ptkpStatus, $variableAllowance, $tkBaseSalaryOnly, $withJp);
        $months = array_fill(1, 11, $monthly);

        $annualGross = $monthly['gross'] * 12;
        $biayaJabatan = min(
            $this->pct($annualGross, $this->rules['biaya_jabatan']['rate']),
            $this->rules['biaya_jabatan']['annual_cap'],
        );
        $pensionContributions = ($monthly['bpjs_employee']['jht'] + $monthly['bpjs_employee']['jp']) * 12;
        $neto = $annualGross - $biayaJabatan - $pensionContributions;

        // PKP is rounded down to the thousand before applying Pasal 17.
        $pkp = intdiv(max(0, $neto - $this->ptkp($ptkpStatus)), 1000) * 1000;
        $annualTax = $this->progressive($pkp);

        $december = $monthly;
        $december['ter_rate'] = null;
        $december['pph21'] = $annualTax - $monthly['pph21'] * 11;
        $december['take_home'] = $monthly['take_home'] + $monthly['pph21'] - $december['pph21'];
        $months[12] = $december;

        return [
            'months' => $months,
            'annual' => [
                'gross' => $annualGross,
                'biaya_jabatan' => $biayaJabatan,
                'pension_contributions' => $pensionContributions,
                'neto' => $neto,
                'ptkp' => $this->ptkp($ptkpStatus),
                'pkp' => $pkp,
                'pph21' => $annualTax,
            ],
        ];
    }

    public function ptkp(string $status): int
    {
        [$marital, $dependents] = explode('/', $status);
        $p = $this->rules['ptkp'];

        return $p['self']
            + ($marital === 'K' ? $p['married'] : 0)
            + min((int) $dependents, $p['max_dependents']) * $p['per_dependent'];
    }

    private function progressive(int $pkp): int
    {
        $tax = 0;
        $lower = 0;
        foreach ($this->rules['pasal_17'] as [$upper, $rate]) {
            $slice = ($upper === null ? $pkp : min($pkp, $upper)) - $lower;
            if ($slice <= 0) {
                break;
            }
            $tax += $this->pct($slice, $rate);
            $lower = $upper;
        }

        return $tax;
    }

    private function bracket(array $table, int $amount): int
    {
        foreach ($table as [$upper, $rate]) {
            if ($upper === null || $amount <= $upper) {
                return $rate;
            }
        }
    }

    private function capped(int $amount, ?int $cap): int
    {
        return $cap === null ? $amount : min($amount, $cap);
    }

    private function pct(int $amount, int $basisPoints): int
    {
        return intdiv($amount * $basisPoints, 10_000);
    }
}
