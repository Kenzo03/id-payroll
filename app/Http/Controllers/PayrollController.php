<?php

namespace App\Http\Controllers;

use App\Payroll\PayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    // GET, not POST: a calculation changes nothing, so reload and shareable links just work.
    public function __invoke(Request $request)
    {
        $statuses = array_keys(config('payroll.ter_category'));

        if (! $request->has('salary')) {
            return view('payroll', ['statuses' => $statuses]);
        }

        // Inputs arrive with thousand separators (10.000.000).
        $request->merge(array_map(
            fn ($v) => is_string($v) ? str_replace('.', '', $v) : $v,
            $request->only(['salary', 'allowance', 'variable_allowance']),
        ));

        $validator = Validator::make($request->all(), [
            'salary' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'allowance' => ['nullable', 'integer', 'min:0', 'max:10000000000'],
            'variable_allowance' => ['nullable', 'integer', 'min:0', 'max:10000000000'],
            'tk_base_salary_only' => ['nullable', 'boolean'],
            'no_jp' => ['nullable', 'boolean'],
            'ptkp_status' => ['required', Rule::in($statuses)],
        ], [
            'required' => ':attribute wajib diisi.',
            'integer' => ':attribute harus berupa angka bulat.',
            'min' => ':attribute tidak boleh negatif.',
            'max' => ':attribute terlalu besar.',
            'in' => ':attribute tidak valid.',
        ], [
            'salary' => 'Gaji pokok',
            'allowance' => 'Tunjangan tetap',
            'variable_allowance' => 'Tunjangan tidak tetap',
            'ptkp_status' => 'Status PTKP',
        ]);

        // Render errors in place; redirecting back on GET would loop to the same URL.
        if ($validator->fails()) {
            return view('payroll', ['statuses' => $statuses, 'input' => $request->all()])
                ->withErrors($validator);
        }
        $input = $validator->validated();

        $result = (new PayrollCalculator(config('payroll')))
            ->year(
                $input['salary'],
                $input['allowance'] ?? 0,
                $input['ptkp_status'],
                $input['variable_allowance'] ?? 0,
                $request->boolean('tk_base_salary_only'),
                withJp: ! $request->boolean('no_jp'),
            );

        return view('payroll', ['statuses' => $statuses, 'input' => $request->all(), 'result' => $result]);
    }
}
