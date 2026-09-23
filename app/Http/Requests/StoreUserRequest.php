<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Rules\ValidPin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff/Admin: name, role and a starting PIN (they choose their own at first
 * sign-in); email optional. Superadmin: email invitation, as before.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSuperadmin = $this->input('role') === UserRole::Superadmin->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'email' => [Rule::requiredIf($isSuperadmin), 'nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'pin' => ['exclude_if:role,'.UserRole::Superadmin->value, 'required', 'string', 'confirmed', ValidPin::starting()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['pin' => __('starting PIN')];
    }
}
