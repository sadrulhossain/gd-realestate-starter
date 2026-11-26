<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRentChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('rent_charge')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'due_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
