<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Correcting a weighed line that is already on the bill.
 *
 * Both numbers are re-keyed from the scale, exactly as they were the first
 * time — there is no tare field, because the hardware's TARE button
 * already produced the net figure.
 *
 * The reason is mandatory regardless of how small the change is. It is not
 * only for the audit trail: it is the thing that explains to whoever reads
 * the order later why revision 2 exists at all.
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
            'net_grams' => ['required', 'integer', 'min:1', 'max:200000'],
            'amount_charged' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'pieces' => ['nullable', 'integer', 'min:1', 'max:999'],
            'cooking_style_id' => ['nullable', 'integer', 'exists:cooking_styles,id'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ((int) $this->input('tare_grams', 0) > 0) {
                $validator->errors()->add('tare_grams', __('Tare is handled by the scale itself — send the net weight in net_grams.'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function correction(): array
    {
        $data = $this->validated();

        // The recorder reads both names. A correction's reason is also its
        // variance reason — a correction is by definition a departure from
        // what the line previously said.
        $data['variance_reason'] = $data['reason'];

        return $data;
    }
}
