<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\LeaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeLeaseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lease')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LeaseStatus::class)],
            'ended_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
