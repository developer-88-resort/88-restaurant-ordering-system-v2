<?php

namespace App\Http\Requests;

use App\Enums\SpaceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateSpaceRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:100'],
            'status' => ['required', new Enum(SpaceStatus::class)],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'shared_space_ids' => ['nullable', 'array'],
            'shared_space_ids.*' => ['integer', 'exists:spaces,id'],
        ];
    }
}
