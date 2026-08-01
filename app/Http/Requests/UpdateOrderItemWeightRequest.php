<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Correcting the scale reading on an already-placed weighed line. The
 * reason is mandatory: this moves money on an order the customer may
 * already be looking at, so every correction has to say why.
 */
class UpdateOrderItemWeightRequest extends FormRequest
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
            'weight_grams' => ['required', 'integer', 'min:1', 'max:200000'],
            'tare_grams' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'pieces' => ['nullable', 'integer', 'min:1', 'max:999'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('weight_grams') && (int) $this->input('tare_grams', 0) >= (int) $this->input('weight_grams')) {
                $validator->errors()->add('tare_grams', __('The tare weight must be less than the weight on the scale.'));
            }
        });
    }
}
