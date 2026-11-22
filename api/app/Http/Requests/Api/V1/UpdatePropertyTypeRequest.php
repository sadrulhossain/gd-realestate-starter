<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PropertyType;
use App\Support\Bilingual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('property_type')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...Bilingual::rules([
                'name' => ['sometimes', 'required', 'string', 'max:100'],
            ]),
            'key' => ['sometimes', 'required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('property_types', 'key')->ignore($this->route('property_type'))],
            'group' => ['sometimes', 'required', Rule::in(PropertyType::GROUPS)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
