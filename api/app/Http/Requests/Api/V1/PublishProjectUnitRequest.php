<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublishProjectUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publish', $this->route('unit')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'listing_type' => ['sometimes', Rule::in(Property::LISTING_TYPES)],
            'rent_period' => ['sometimes', 'nullable', Rule::in(Property::RENT_PERIODS)],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->input('listing_type', 'sale');

            if ($type === 'rent' && blank($this->input('rent_period'))) {
                $validator->errors()->add('rent_period', 'A rent listing needs a rent period (month, year or day).');
            }

            if ($type === 'sale' && $this->filled('rent_period')) {
                $validator->errors()->add('rent_period', 'A sale listing has no rent period.');
            }
        }];
    }
}
