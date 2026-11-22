<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\PropertyRules;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePropertyRequest extends FormRequest
{
    use PropertyRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Property::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->propertyRules(true);
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkRentPeriod($validator, null)];
    }
}
