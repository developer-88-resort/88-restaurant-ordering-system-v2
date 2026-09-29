<?php

namespace App\Http\Requests;

use App\Models\SpaceCategory;
use App\Support\SpaceNaming;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreBulkSpacesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The prefix is only asked for when the category has no spaces yet; after
     * that every new space takes the category's existing prefix
     * (App\Support\SpaceNaming) and a typed one is ignored.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:space_categories,id'],
            'prefix' => ['nullable', 'string', 'max:30', 'regex:/\S/u', $this->prefixForEmptyCategory(...)],
            'start' => ['required', 'integer', 'min:1', 'max:9999'],
            'count' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function prefixForEmptyCategory(string $attribute, mixed $value, Closure $fail): void
    {
        $category = SpaceCategory::find($this->integer('category_id'));

        if (! $category || SpaceNaming::prefix($category) !== null) {
            return;
        }

        if ($owner = SpaceNaming::prefixOwner(trim((string) $value), $category)) {
            $fail(__('":prefix" is already used by :category. Pick a different name for this category\'s spaces.', ['prefix' => trim((string) $value), 'category' => $owner->name]));
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $category = SpaceCategory::find($this->integer('category_id'));

            if ($category && SpaceNaming::prefix($category) === null && trim((string) $this->input('prefix')) === '') {
                $validator->errors()->add('prefix', __('Name the spaces in :category (e.g. "Table") — every space after this one will use the same name.', ['category' => $category->name]));
            }
        });
    }
}
