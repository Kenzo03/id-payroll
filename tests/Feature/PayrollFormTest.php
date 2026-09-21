<?php

namespace Tests\Feature;

use Tests\TestCase;

class PayrollFormTest extends TestCase
{
    public function test_form_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('Status PTKP');
    }

    public function test_calculation_shows_monthly_and_december_tax(): void
    {
        $this->get('/?'.http_build_query(['salary' => 10_000_000, 'allowance' => 0, 'ptkp_status' => 'TK/0']))
            ->assertOk()
            ->assertSee('Rp 261.350')
            ->assertSee('Rp 3.277.200');
    }

    public function test_shows_gross_income_breakdown(): void
    {
        $this->get('/?'.http_build_query(['salary' => '9.000.000', 'allowance' => '2.500.000', 'ptkp_status' => 'TK/0']))
            ->assertOk()
            ->assertSeeInOrder(['Rincian penghasilan bruto', 'Rp 460.000', 'Rp 27.600', 'Rp 34.500', 'Rp 12.022.100']);
    }

    public function test_accepts_thousand_separators(): void
    {
        $this->get('/?'.http_build_query(['salary' => '10.000.000', 'allowance' => '0', 'ptkp_status' => 'TK/0']))
            ->assertOk()
            ->assertSee('Rp 261.350')
            ->assertSee('value="10.000.000"', false);
    }

    public function test_bpjs_tk_base_option_is_applied_and_kept_checked(): void
    {
        $this->get('/?'.http_build_query(['salary' => '8.000.000', 'allowance' => '1.000.000', 'ptkp_status' => 'K/0', 'tk_base_salary_only' => '1']))
            ->assertOk()
            ->assertSee('Rp 164.556')
            ->assertSee('value="1" checked', false);
    }

    public function test_no_jp_option_is_applied_and_kept_checked(): void
    {
        $this->get('/?'.http_build_query(['salary' => '8.000.000', 'allowance' => '1.000.000', 'ptkp_status' => 'K/0', 'no_jp' => '1']))
            ->assertOk()
            ->assertSee('Rp 164.650')
            ->assertSee('name="no_jp" value="1" checked', false)
            ->assertSee('<option selected>K/0</option>', false)
            ->assertDontSee('<option selected>K/3</option>', false);
    }

    public function test_reload_is_a_plain_get(): void
    {
        $this->post('/', ['salary' => 10_000_000, 'ptkp_status' => 'TK/0'])->assertMethodNotAllowed();
    }

    public function test_pdf_button_only_with_result(): void
    {
        $this->get('/')->assertDontSee('Simpan PDF');
        $this->get('/?'.http_build_query(['salary' => '8.000.000', 'ptkp_status' => 'K/1', 'no_jp' => '1']))
            ->assertSee('Simpan PDF')
            ->assertSee('<title>Perhitungan gaji dan PPh 21 K/1</title>', false)
            ->assertSee('Kebijakan perusahaan: tidak ikut JP');
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->get('/?'.http_build_query(['salary' => -1, 'ptkp_status' => 'X/9']))
            ->assertOk()
            ->assertSee('Gaji pokok tidak boleh negatif.')
            ->assertSee('Status PTKP tidak valid.');
    }
}
