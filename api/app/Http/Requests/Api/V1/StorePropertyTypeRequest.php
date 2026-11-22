<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PropertyType;
use App\Support\Bilingual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PropertyType::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...Bilingual::rules([
                'name' => ['required', 'string', 'max:100'],
            ]),
            'key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('property_types', 'key')],
            'group' => ['required', Rule::in(PropertyType::GROUPS)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
