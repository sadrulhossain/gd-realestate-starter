<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PropertyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePropertyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('property')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PropertyStatus::class)],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
