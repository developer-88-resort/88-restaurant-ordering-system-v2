<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
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
            // Email is optional for Staff/Admin (they sign in with a PIN);
            // a Superadmin signs in with it, so it stays required there.
            'email' => [Rule::requiredIf($isSuperadmin), 'nullable', 'string', 'email', 'max:255', 'unique:users,email,'.$this->route('user')->id],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $user = $this->route('user');

                // A Superadmin can only sign in with a password, so a PIN-only
                // account moved up to Superadmin would be locked out.
                if ($this->input('role') === UserRole::Superadmin->value
                    && $user->role !== UserRole::Superadmin
                    && $user->password === null) {
                    $validator->errors()->add('role', __(':name has no password yet. A Superadmin signs in with email and password — send them a password reset link first, then change the role.', ['name' => $user->name]));
                }
            },
        ];
    }
}
