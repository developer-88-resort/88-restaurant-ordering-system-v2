<?php

namespace App\Http\Requests;

use App\Enums\SpaceStatus;
use App\Support\SpaceNaming;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'name' => ['required', 'string', 'max:100', $this->followsCategoryNaming(...)],
            'status' => ['required', new Enum(SpaceStatus::class)],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'shared_space_ids' => ['nullable', 'array'],
            'shared_space_ids.*' => ['integer', Rule::exists('spaces', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * Only a rename is checked: a space named before the naming rule can
     * still have its status or capacity saved without being forced to change
     * its name, but any new name must follow the category's prefix.
     */
    protected function followsCategoryNaming(string $attribute, mixed $value, Closure $fail): void
    {
        $space = $this->route('space');

        if (! $space?->category || trim((string) $value) === $space->name) {
            return;
        }

        if ($problem = SpaceNaming::problem((string) $value, $space->category, $space->id)) {
            $fail($problem);
        }
    }
}
