<?php

namespace App\Http\Requests\Api\V1;

class UpdateMaintenanceRequest extends StoreMaintenanceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('maintenance')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_map(
            fn (array $rules) => $rules[0] === 'sometimes' ? $rules : ['sometimes', ...$rules],
            parent::rules(),
        );
    }
}
