<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('superadmin.settings.edit', [
            'setting' => Setting::current(),
        ]);
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        Setting::current()->update([
            ...$request->validated(),
            'prices_include_vat' => $request->boolean('prices_include_vat'),
            'service_charge_enabled' => $request->boolean('service_charge_enabled'),
            'service_charge_taxable' => $request->boolean('service_charge_taxable'),
            'weigh_customer_confirmation_enabled' => $request->boolean('weigh_customer_confirmation_enabled'),
            'weighed_print_slip' => $request->boolean('weighed_print_slip'),
            'reveal_full_discount_id_on_pdf' => $request->boolean('reveal_full_discount_id_on_pdf'),
        ]);

        return redirect()->route('superadmin.settings.edit')->with('status', __('Settings updated successfully.'));
    }
}
