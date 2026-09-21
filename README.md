# Indonesian Payroll Calculator

A small Laravel app that calculates monthly payroll for a permanent employee in Indonesia: BPJS contributions, PPh 21 withholding with the TER method (PP 58/2023), the December true-up, and take-home pay.

## Inputs

- Gaji pokok, tunjangan tetap, and tunjangan tidak tetap (transport or meal paid per attendance: taxable, but not part of the BPJS base)
- PTKP status (TK/0 to K/3), as of 1 January
- Company policy options, because real payslips differ here:
  - JHT, JP, JKK and JKM calculated on gaji pokok only
  - Employee not enrolled in JP

The result is shown as a monthly payslip, a 12-month table, the gross income breakdown, and the December PPh 21 calculation. The form uses GET, so every scenario has a shareable URL.

## What it calculates

| Component | Rule |
|---|---|
| BPJS Kesehatan | Employer 4%, employee 1%, salary base capped at Rp12,000,000 |
| JHT | Employer 3.7%, employee 2%, no cap |
| JP | Employer 2%, employee 1%, salary base capped (revised every March) |
| JKK / JKM | Employer only, 0.24% (lowest risk class) / 0.3% |
| PPh 21, Jan–Nov | Monthly taxable gross × TER rate for the employee's category (A/B/C) |
| PPh 21, December | Annual tax under Pasal 17 minus what was withheld Jan–Nov |

**Taxable gross** = salary + fixed allowance + employer-paid BPJS Kesehatan, JKK and JKM. Employer JHT and JP are not taxable income.

**December true-up:**

```
annual gross
− biaya jabatan (5%, max Rp6,000,000/year)
− employee JHT + JP
= net income
− PTKP
= PKP (rounded down to the thousand)
→ Pasal 17: 5% / 15% / 25% / 30% / 35%
```

## Where the rules live

All rates, caps and TER tables are in [`config/payroll.php`](config/payroll.php). The calculation is in [`app/Payroll/PayrollCalculator.php`](app/Payroll/PayrollCalculator.php). Rates are stored in basis points so all money math stays in integer rupiah, with no float rounding.

When a regulation changes (for example the yearly JP cap), you only edit the config file.

## Run it

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Open http://127.0.0.1:8000.

## Tests

```bash
php artisan test
```

The unit tests check hand-calculated cases: TER withholding, the December true-up, BPJS caps, income below PTKP, and PTKP amounts.

## Not covered

- Mid-year joiners and leavers
- THR, bonuses and other irregular income
- Overtime (PP 35/2021)
- Non-resident employees (PPh 26)

## References

- PP 58 Tahun 2023: TER categories and rate tables (Lampiran A–C)
- PMK 168 Tahun 2023: PPh 21 withholding procedure, taxable employer contributions (Pasal 5), deductions (Pasal 10)
- PP 45 Tahun 2015 and BPJS TK letter B/1226/022026: JP salary cap Rp11,086,300 from March 2026
- UU 7 Tahun 2021 (HPP): Pasal 17 brackets
- PMK 101/PMK.010/2016: PTKP
- Perpres 64 Tahun 2020: BPJS Kesehatan

Rates reflect the regulations at the time of writing. Check them against the current rules before using the output for real payroll.
