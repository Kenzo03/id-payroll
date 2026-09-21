@php($rp = fn ($n) => ($n < 0 ? '-' : '') . 'Rp ' . number_format(abs($n), 0, ',', '.'))
@php($num = fn ($v) => is_numeric($d = str_replace('.', '', (string) $v)) ? number_format((int) $d, 0, ',', '.') : $v)
@php($pct = fn ($bps) => $bps === null ? 'Pasal 17' : rtrim(rtrim(number_format($bps / 100, 2, ',', '.'), '0'), ',') . '%')
@php($months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($result) ? 'Perhitungan gaji dan PPh 21 ' . $input['ptkp_status'] : 'Kalkulator Gaji dan PPh 21' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --paper: #EDF0F3;
            --slip: #FFFFFF;
            --ink: #16283A;
            --muted: #5A6978;
            --rule: #D5DCE3;
            --accent: #1D4E89;
            --paid: #17694A;
            --deduct: #9B3434;
            --radius: 10px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font: 400 15px/1.55 'Plus Jakarta Sans', system-ui, sans-serif;
            font-variant-numeric: tabular-nums;
        }

        .page { max-width: 1120px; margin: 0 auto; padding: 48px 24px 64px; }

        h1, h2, h3 { margin: 0; line-height: 1.2; }
        h1 { font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; }
        h2 { font-size: 1.2rem; font-weight: 700; }
        h3 { font-size: .95rem; font-weight: 600; color: var(--muted); }

        .intro { max-width: 62ch; margin: 10px 0 36px; color: var(--muted); }

        /* Inputs and slip side by side; the slip is the one strong element on the page. */
        .workspace {
            display: grid;
            grid-template-columns: minmax(280px, 340px) 1fr;
            gap: 40px;
            align-items: start;
        }

        form { display: grid; gap: 18px; }

        .field { display: grid; gap: 6px; }
        .field > span { font-size: .875rem; font-weight: 600; }
        .field small { color: var(--muted); font-size: .8rem; }

        .money {
            display: flex;
            align-items: center;
            background: var(--slip);
            border: 1px solid var(--rule);
            border-radius: 8px;
        }
        .money:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px #1D4E8933; }
        .money b { padding-left: 12px; color: var(--muted); font-weight: 500; }
        .money input {
            flex: 1;
            min-width: 0;
            border: 0;
            background: transparent;
            padding: 10px 12px 10px 8px;
            font: inherit;
            font-weight: 600;
            text-align: right;
            color: var(--ink);
        }
        .money input:focus { outline: none; }

        select {
            font: inherit;
            font-weight: 600;
            color: var(--ink);
            padding: 10px 12px;
            border: 1px solid var(--rule);
            border-radius: 8px;
            background: var(--slip);
        }

        fieldset { border: 0; border-top: 1px solid var(--rule); margin: 4px 0 0; padding: 16px 0 0; display: grid; gap: 10px; }
        legend { font-size: .875rem; font-weight: 600; padding: 0; margin-bottom: 10px; }
        .check { display: flex; gap: 10px; align-items: flex-start; font-size: .9rem; }
        .check input { accent-color: var(--accent); width: 16px; height: 16px; margin: 3px 0 0; flex: none; }

        button {
            font: inherit;
            font-weight: 700;
            color: #fff;
            background: var(--accent);
            border: 0;
            border-radius: 8px;
            padding: 12px 16px;
            cursor: pointer;
        }
        button:hover { background: #173F70; }

        :is(select, button, .check input):focus-visible { outline: 3px solid #1D4E8966; outline-offset: 2px; }

        .errors {
            margin: 0;
            padding: 12px 16px 12px 32px;
            border-radius: 8px;
            background: #9B34340F;
            color: var(--deduct);
            font-size: .9rem;
        }

        /* The slip gaji */
        .slip {
            background: var(--slip);
            border-radius: var(--radius);
            box-shadow: 0 1px 2px #16283A14, 0 12px 32px -12px #16283A33;
        }
        .slip-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 24px 28px 20px; border-bottom: 1.5px dashed var(--rule); }
        .print {
            flex: none;
            font-size: .85rem;
            font-weight: 600;
            color: var(--accent);
            background: transparent;
            border: 1px solid var(--rule);
            padding: 7px 12px;
        }
        .print:hover { background: #1D4E890F; }
        .slip-head p { margin: 4px 0 0; color: var(--muted); font-size: .9rem; }

        .slip-body { display: grid; grid-template-columns: 1fr 1fr; }
        .slip-body section { padding: 20px 28px 24px; }
        .slip-body section + section { border-left: 1px solid var(--rule); }
        .slip-body h3 { margin-bottom: 10px; }

        /* No column gap, so the rule above a total runs unbroken across both columns. */
        .lines { display: grid; grid-template-columns: 1fr auto; row-gap: 8px; margin: 0; }
        .lines dt { color: var(--ink); padding-right: 16px; }
        .lines dt small { display: block; color: var(--muted); font-size: .78rem; }
        .lines dd { margin: 0; text-align: right; font-weight: 500; }
        .lines .sum { padding-top: 10px; border-top: 1px solid var(--rule); font-weight: 700; }
        .deductions dd { color: var(--deduct); }
        .deductions dd.sum { color: var(--deduct); }

        .slip-total {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 16px;
            flex-wrap: wrap;
            padding: 22px 28px;
            border-top: 1.5px dashed var(--rule);
        }
        .slip-total span { font-weight: 600; }
        .slip-total strong { font-size: clamp(1.9rem, 4vw, 2.6rem); font-weight: 800; letter-spacing: -0.02em; color: var(--paid); }
        .amt { white-space: nowrap; }
        .slip-foot { margin: 0; padding: 0 28px 24px; color: var(--muted); font-size: .88rem; }

        .slip-empty { padding: 56px 28px; color: var(--muted); max-width: 48ch; }
        .slip-empty h2 { color: var(--ink); margin-bottom: 8px; }

        /* Supporting detail, quieter than the slip */
        .detail { margin-top: 64px; display: grid; gap: 56px; }
        .detail h2 { margin-bottom: 4px; }
        .detail header p { margin: 0 0 16px; color: var(--muted); font-size: .9rem; max-width: 70ch; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th { font-weight: 600; color: var(--muted); text-align: right; padding: 0 12px 10px; white-space: nowrap; border-bottom: 1.5px solid var(--ink); }
        td { text-align: right; padding: 9px 12px; border-bottom: 1px solid var(--rule); white-space: nowrap; }
        th:first-child, td:first-child { text-align: left; padding-left: 0; }
        th:last-child, td:last-child { padding-right: 0; }
        td.thp { font-weight: 700; color: var(--paid); }
        tr.dec td { background: #1D4E890A; font-weight: 600; }
        tr.dec td.thp { font-weight: 700; }

        .pair { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; }
        .pair .lines { row-gap: 10px; }
        .pair .lines dt { color: var(--ink); }
        .pair .lines dd.minus { color: var(--deduct); }

        footer { margin-top: 64px; padding-top: 20px; border-top: 1px solid var(--rule); color: var(--muted); font-size: .82rem; max-width: 80ch; }
        footer p { margin: 0 0 6px; }

        @media (max-width: 960px) {
            .page { padding: 32px 16px 48px; }
            h1 { font-size: 1.6rem; }
            .workspace, .pair { grid-template-columns: 1fr; gap: 32px; }
        }
        @media (max-width: 560px) {
            .slip-body { grid-template-columns: 1fr; }
            .slip-body section + section { border-left: 0; border-top: 1px solid var(--rule); }
            .slip-head, .slip-body section, .slip-total { padding-left: 20px; padding-right: 20px; }
            .slip-foot { padding: 0 20px 20px; }
        }

        /* Print / Save as PDF: an A4 document with the results only, no form. */
        @page { size: A4; margin: 14mm; }
        @media print {
            :root { --paper: #FFFFFF; }
            body { font-size: 10pt; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .page { max-width: none; padding: 0; }
            .intro { margin-bottom: 16px; }
            .workspace { display: block; }
            .workspace > form, .print { display: none; }
            .slip { box-shadow: none; border: 1px solid var(--rule); }
            .slip-total strong { font-size: 22pt; }
            .slip, table, .pair section { break-inside: avoid; }
            .detail { margin-top: 24px; gap: 24px; }
            .pair { grid-template-columns: 1fr 1fr; gap: 28px; }
            footer { margin-top: 24px; }
        }
    </style>
</head>
<body>
<div class="page">
    <h1>Kalkulator Gaji dan PPh 21</h1>
    <p class="intro">Hitung potongan BPJS, PPh 21 metode TER, dan take home pay pegawai tetap berdasarkan PP 58/2023 dan PMK 168/2023.</p>

    <div class="workspace">
        <form method="get" action="/">
            @if ($errors->any())
                <ul class="errors">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif

            <label class="field">
                <span>Gaji pokok per bulan</span>
                <span class="money"><b>Rp</b><input type="text" inputmode="numeric" data-thousands name="salary" required value="{{ $num($input['salary'] ?? 10000000) }}"></span>
            </label>
            <label class="field">
                <span>Tunjangan tetap</span>
                <span class="money"><b>Rp</b><input type="text" inputmode="numeric" data-thousands name="allowance" value="{{ $num($input['allowance'] ?? 0) }}"></span>
                <small>Dibayar tetap tiap bulan, misalnya tunjangan jabatan. Ikut dasar iuran BPJS.</small>
            </label>
            <label class="field">
                <span>Tunjangan tidak tetap</span>
                <span class="money"><b>Rp</b><input type="text" inputmode="numeric" data-thousands name="variable_allowance" value="{{ $num($input['variable_allowance'] ?? 0) }}"></span>
                <small>Misalnya transport atau makan per kehadiran. Kena pajak, tidak ikut dasar BPJS.</small>
            </label>
            <label class="field">
                <span>Status PTKP</span>
                <select name="ptkp_status">
                    @foreach ($statuses as $status)
                        <option @selected(($input['ptkp_status'] ?? 'TK/0') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <small>TK tidak kawin, K kawin, angka jumlah tanggungan per 1 Januari.</small>
            </label>

            <fieldset>
                <legend>Kebijakan perusahaan</legend>
                <label class="check">
                    <input type="checkbox" name="tk_base_salary_only" value="1" @checked($input['tk_base_salary_only'] ?? false)>
                    <span>JHT, JP, JKK, dan JKM dihitung dari gaji pokok saja</span>
                </label>
                <label class="check">
                    <input type="checkbox" name="no_jp" value="1" @checked($input['no_jp'] ?? false)>
                    <span>Tidak ikut JP (Jaminan Pensiun)</span>
                </label>
            </fieldset>

            <button type="submit">Hitung gaji</button>
        </form>

        @isset($result)
            @php($jan = $result['months'][1])
            @php($dec = $result['months'][12])
            @php($emp = $jan['bpjs_employee'])
            <article class="slip" aria-labelledby="slip-title">
                <div class="slip-head">
                    <div>
                        <h2 id="slip-title">Slip gaji bulanan</h2>
                        <p>Januari sampai November, status {{ $input['ptkp_status'] }}, TER kategori {{ $jan['ter_category'] }}</p>
                        @if (($input['tk_base_salary_only'] ?? false) || ($input['no_jp'] ?? false))
                            <p>Kebijakan perusahaan: {{ implode(', ', array_filter([
                                ($input['tk_base_salary_only'] ?? false) ? 'BPJS Ketenagakerjaan dari gaji pokok' : null,
                                ($input['no_jp'] ?? false) ? 'tidak ikut JP' : null,
                            ])) }}</p>
                        @endif
                    </div>
                    <button type="button" class="print" onclick="window.print()">Simpan PDF</button>
                </div>
                <div class="slip-body">
                    <section>
                        <h3>Penghasilan</h3>
                        <dl class="lines">
                            <dt>Gaji pokok</dt><dd>{{ $rp($jan['salary']) }}</dd>
                            @if ($jan['allowance'])<dt>Tunjangan tetap</dt><dd>{{ $rp($jan['allowance']) }}</dd>@endif
                            @if ($jan['variable_allowance'])<dt>Tunjangan tidak tetap</dt><dd>{{ $rp($jan['variable_allowance']) }}</dd>@endif
                            <dt class="sum">Total</dt><dd class="sum">{{ $rp($jan['salary'] + $jan['allowance'] + $jan['variable_allowance']) }}</dd>
                        </dl>
                    </section>
                    <section class="deductions">
                        <h3>Potongan</h3>
                        <dl class="lines">
                            <dt>BPJS Kesehatan</dt><dd>{{ $rp($emp['kesehatan']) }}</dd>
                            <dt>BPJS TK, JHT</dt><dd>{{ $rp($emp['jht']) }}</dd>
                            @if ($emp['jp'])<dt>BPJS TK, JP</dt><dd>{{ $rp($emp['jp']) }}</dd>@endif
                            <dt>PPh 21 <small>TER {{ $pct($jan['ter_rate']) }} dari bruto {{ $rp($jan['gross']) }}</small></dt><dd>{{ $rp($jan['pph21']) }}</dd>
                            <dt class="sum">Total</dt><dd class="sum">{{ $rp(array_sum($emp) + $jan['pph21']) }}</dd>
                        </dl>
                    </section>
                </div>
                <div class="slip-total">
                    <span>Take home pay</span>
                    <strong>{{ $rp($jan['take_home']) }}</strong>
                </div>
                <p class="slip-foot">Desember memakai tarif Pasal 17 setahun: PPh 21 <span class="amt">{{ $rp($dec['pph21']) }}</span>, take home pay <span class="amt">{{ $rp($dec['take_home']) }}</span>.</p>
            </article>
        @else
            <div class="slip">
                <div class="slip-empty">
                    <h2>Slip gaji muncul di sini</h2>
                    <p>Isi gaji pokok, tunjangan, dan status PTKP, lalu tekan Hitung gaji. Hasilnya berupa slip bulanan, rincian 12 bulan, dan perhitungan PPh 21 Desember.</p>
                </div>
            </div>
        @endisset
    </div>

    @isset($result)
        @php($bpjs = config('payroll.bpjs'))
        @php($tkBase = ($input['tk_base_salary_only'] ?? false) ? 'gaji pokok' : 'gaji pokok + tunjangan tetap')
        <div class="detail">
            <section>
                <header>
                    <h2>Rincian 12 bulan</h2>
                    <p>Januari sampai November memakai tarif TER bulanan. Desember menghitung ulang pajak setahun, jadi potongannya bisa berbeda.</p>
                </header>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Bulan</th><th>Penghasilan bruto</th><th>BPJS Kes</th><th>JHT</th><th>JP</th>
                                <th>Tarif TER</th><th>PPh 21</th><th>Take home pay</th><th>Biaya perusahaan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['months'] as $m => $row)
                                <tr @class(['dec' => $m === 12])>
                                    <td>{{ $months[$m - 1] }}</td>
                                    <td>{{ $rp($row['gross']) }}</td>
                                    <td>{{ $rp($row['bpjs_employee']['kesehatan']) }}</td>
                                    <td>{{ $rp($row['bpjs_employee']['jht']) }}</td>
                                    <td>{{ $rp($row['bpjs_employee']['jp']) }}</td>
                                    <td>{{ $pct($row['ter_rate']) }}</td>
                                    <td>{{ $rp($row['pph21']) }}</td>
                                    <td class="thp">{{ $rp($row['take_home']) }}</td>
                                    <td>{{ $rp($row['salary'] + $row['allowance'] + $row['variable_allowance'] + array_sum($row['bpjs_employer'])) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="pair">
                <section>
                    <header>
                        <h2>Rincian penghasilan bruto per bulan</h2>
                        <p>Iuran BPJS Kesehatan, JKK, dan JKM yang dibayar perusahaan dihitung sebagai penghasilan. JHT dan JP dari perusahaan tidak (PMK 168/2023 Pasal 5 ayat 3).</p>
                    </header>
                    <dl class="lines">
                        <dt>Gaji pokok</dt><dd>{{ $rp($jan['salary']) }}</dd>
                        <dt>Tunjangan tetap</dt><dd>{{ $rp($jan['allowance']) }}</dd>
                        <dt>Tunjangan tidak tetap</dt><dd>{{ $rp($jan['variable_allowance']) }}</dd>
                        <dt>BPJS Kesehatan dari perusahaan <small>{{ $pct($bpjs['kesehatan']['employer']) }}, dasar maksimal {{ $rp($bpjs['kesehatan']['cap']) }}</small></dt><dd>{{ $rp($jan['bpjs_employer']['kesehatan']) }}</dd>
                        <dt>JKK dari perusahaan <small>{{ $pct($bpjs['jkk']['employer']) }} dari {{ $tkBase }}</small></dt><dd>{{ $rp($jan['bpjs_employer']['jkk']) }}</dd>
                        <dt>JKM dari perusahaan <small>{{ $pct($bpjs['jkm']['employer']) }} dari {{ $tkBase }}</small></dt><dd>{{ $rp($jan['bpjs_employer']['jkm']) }}</dd>
                        <dt class="sum">Penghasilan bruto</dt><dd class="sum">{{ $rp($jan['gross']) }}</dd>
                    </dl>
                </section>

                <section>
                    <header>
                        <h2>Perhitungan PPh 21 Desember</h2>
                        <p>Pajak setahun dihitung dengan tarif Pasal 17, lalu dikurangi PPh 21 yang sudah dipotong Januari sampai November.</p>
                    </header>
                    <dl class="lines">
                        <dt>Penghasilan bruto setahun</dt><dd>{{ $rp($result['annual']['gross']) }}</dd>
                        <dt>Biaya jabatan <small>5%, maksimal Rp 6.000.000 setahun</small></dt><dd class="minus">-{{ $rp($result['annual']['biaya_jabatan']) }}</dd>
                        <dt>Iuran JHT dan JP pegawai</dt><dd class="minus">-{{ $rp($result['annual']['pension_contributions']) }}</dd>
                        <dt>Penghasilan neto</dt><dd>{{ $rp($result['annual']['neto']) }}</dd>
                        <dt>PTKP {{ $input['ptkp_status'] }}</dt><dd class="minus">-{{ $rp($result['annual']['ptkp']) }}</dd>
                        <dt>PKP <small>dibulatkan ke bawah per seribu</small></dt><dd>{{ $rp($result['annual']['pkp']) }}</dd>
                        <dt class="sum">PPh 21 setahun</dt><dd class="sum">{{ $rp($result['annual']['pph21']) }}</dd>
                        <dt>Sudah dipotong Januari sampai November</dt><dd class="minus">-{{ $rp($jan['pph21'] * 11) }}</dd>
                        <dt class="sum">PPh 21 Desember</dt><dd class="sum">{{ $rp($dec['pph21']) }}</dd>
                    </dl>
                </section>
            </div>
        </div>
    @endisset

    <footer>
        <p>Asumsi: gaji sama setiap bulan, tanpa THR, bonus, lembur, atau pegawai yang masuk di tengah tahun.</p>
        <p>Dasar hitung: PP 58/2023 (tarif TER), PMK 168/2023 (tata cara PPh 21), UU HPP (tarif Pasal 17), batas upah JP {{ $rp(config('payroll.bpjs.jp.cap')) }} per bulan.</p>
    </footer>
</div>

<script>
    // Format as 10.000.000 while typing, keeping the caret after the same digit.
    document.querySelectorAll('[data-thousands]').forEach((el) => el.addEventListener('input', () => {
        const digitsBeforeCaret = el.value.slice(0, el.selectionStart).replace(/\D/g, '').length;
        const digits = el.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
        el.value = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        let pos = 0;
        for (let seen = 0; pos < el.value.length && seen < digitsBeforeCaret; pos++) {
            if (/\d/.test(el.value[pos])) seen++;
        }
        el.setSelectionRange(pos, pos);
    }));
</script>
</body>
</html>
