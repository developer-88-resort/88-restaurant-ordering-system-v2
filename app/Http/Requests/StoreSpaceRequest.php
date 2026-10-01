<?php

namespace App\Http\Requests;

use App\Models\SpaceCategory;
use App\Support\SpaceNaming;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpaceRequest extends FormRequest
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
            'category_id' => ['required', 'exists:space_categories,id'],
            'name' => ['required', 'string', 'max:100', $this->followsCategoryNaming(...)],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** The category's own prefix plus a number not already used there. */
    protected function followsCategoryNaming(string $attribute, mixed $value, Closure $fail): void
    {
        if ($category = SpaceCategory::find($this->integer('category_id'))) {
            if ($problem = SpaceNaming::problem((string) $value, $category)) {
                $fail($problem);
            }
        }
    }
}
