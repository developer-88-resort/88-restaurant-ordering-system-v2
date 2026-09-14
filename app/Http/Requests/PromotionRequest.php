<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The ONE request class behind both creating and updating a banner — same
 * reasoning as MenuItemRequest: store/update rules drift apart the moment
 * they're copies of each other.
 */
class PromotionRequest extends FormRequest
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
            'image' => [
                $this->isMethod('POST') ? 'required' : Rule::requiredIf($this->boolean('remove_image')),
                'image', 'max:2048',
            ],
            'remove_image' => ['nullable', 'boolean'],
            'mobile_image' => ['nullable', 'image', 'dimensions:ratio=3/1', 'max:2048'],
            'remove_mobile_image' => ['nullable', 'boolean'],

            'banner_cta_url' => ['nullable', 'url', 'max:255'],

            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],

            'is_published' => ['nullable', 'boolean'],
            'is_disabled' => ['nullable', 'boolean'],
        ];
    }
}
