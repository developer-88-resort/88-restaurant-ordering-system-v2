<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Moving a slip to another table. Everything that decides WHETHER the move
 * is allowed lives in OrderSlipTransferrer — this only checks the shape of
 * what was submitted, so the UI and an API client are held to the same
 * rules.
 */
class TransferOrderSlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->is_active
            && in_array($user->role, [UserRole::Superadmin, UserRole::Admin, UserRole::Staff], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
            // Null (or absent) means "land on that table as its own slip";
            // an id means "fold into this slip already open there".
            'merge_into_order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'space_id.required' => __('Pick the table this slip is moving to.'),
        ];
    }
}
