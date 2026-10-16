<?php

namespace App\Http\Controllers\Superadmin;

use App\Enums\IncidentImpact;
use App\Events\MaintenanceModeChanged;
use App\Enums\IncidentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingRequest;
use App\Models\Setting;
use App\Models\StatusIncident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('superadmin.settings.edit', [
            'setting' => Setting::current(),
            'activeIncident' => StatusIncident::open()->with('updates')->latest('started_at')->first(),
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

    /**
     * Maintenance Mode on/off (App\Http\Middleware\ShowMaintenancePage).
     * Switching it on opens an incident on the maintenance page's status
     * section (title, impact, affected parts, the message as its first
     * update); switching it off resolves that incident. Kept apart from
     * update() so the switch never depends on the settings form validating.
     */
    public function updateMaintenance(Request $request): RedirectResponse
    {
        $turningOn = $request->boolean('maintenance_mode');

        $data = $request->validate([
            'maintenance_mode' => ['required', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
            'incident_title' => ['nullable', 'string', 'max:150'],
            'incident_impact' => [Rule::requiredIf($turningOn), 'nullable', Rule::enum(IncidentImpact::class)],
            'incident_components' => [Rule::requiredIf($turningOn), 'nullable', 'array', 'min:1'],
            'incident_components.*' => [Rule::in(array_keys(StatusIncident::COMPONENTS))],
        ], [
            'incident_components.required' => __('Choose at least one affected part.'),
            'incident_components.min' => __('Choose at least one affected part.'),
        ]);

        $message = filled($data['maintenance_message'] ?? null) ? trim($data['maintenance_message']) : null;

        DB::transaction(function () use ($turningOn, $data, $message, $request) {
            $open = StatusIncident::open()->latest('started_at')->first();

            if ($turningOn && ! $open) {
                $incident = StatusIncident::create([
                    'title' => filled($data['incident_title'] ?? null) ? trim($data['incident_title']) : __('Scheduled maintenance'),
                    'impact' => $data['incident_impact'],
                    'components' => array_values(array_unique($data['incident_components'])),
                    'started_at' => now(),
                    'created_by' => $request->user()->id,
                ]);
                $incident->updates()->create([
                    'status' => IncidentStatus::InProgress,
                    'message' => $message ?? __('This website is currently undergoing scheduled maintenance. We should be back shortly.'),
                    'created_by' => $request->user()->id,
                ]);
            }

            if (! $turningOn && $open) {
                $open->updates()->create([
                    'status' => IncidentStatus::Resolved,
                    'message' => $message ?? __('This incident has been resolved. Everything is working normally again.'),
                    'created_by' => $request->user()->id,
                ]);
                $open->update(['resolved_at' => now()]);
            }

            Setting::current()->update([
                'maintenance_mode' => $turningOn,
                'maintenance_message' => $turningOn ? $message : null,
            ]);
        });

        // Every open screen switches at once (resources/js/lib/maintenance-watch.js).
        // A Reverb hiccup must not undo the switch, which is already saved.
        rescue(fn () => broadcast(new MaintenanceModeChanged($turningOn)), report: true);

        return redirect()->route('superadmin.settings.edit')->with('status', $turningOn
            ? __('Maintenance Mode is on. Only Superadmin can use the system.')
            : __('Maintenance Mode is off. Everyone can use the system again.'));
    }

    /**
     * A timeline update on the open maintenance incident ("Identified -
     * Nakita na …"), shown on the status page within a minute.
     */
    public function postStatusUpdate(Request $request, StatusIncident $incident): RedirectResponse
    {
        abort_if($incident->resolved_at !== null, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::postableStatuses())],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $incident->updates()->create([
            'status' => $data['status'],
            'message' => trim($data['message']),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('superadmin.settings.edit')->with('status', __('Status update posted.'));
    }

    protected static function postableStatuses(): array
    {
        return array_map(fn (IncidentStatus $status) => $status->value, IncidentStatus::postable());
    }
}
