<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Amenity;
use App\Support\Bilingual;
use Illuminate\Foundation\Http\FormRequest;

class StoreAmenityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Amenity::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...Bilingual::rules([
                'name' => ['required', 'string', 'max:100'],
            ]),
            'icon' => ['sometimes', 'nullable', 'string', 'max:60'],
            'group' => ['sometimes', 'nullable', 'string', 'max:60'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
