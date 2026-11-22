<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\PropertyRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePropertyRequest extends FormRequest
{
    use PropertyRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('property')) ?? false;
    }

    /**
     * The status is not editable here: it moves through POST properties/{id}/status.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->propertyRules(false), 'status' => ['prohibited']];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkRentPeriod($validator, $this->route('property'))];
    }
}
