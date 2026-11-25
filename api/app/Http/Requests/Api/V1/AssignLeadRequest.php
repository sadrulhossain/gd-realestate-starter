<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign', $this->route('lead')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['agent_id' => ['present', 'nullable', 'integer', Rule::exists('agents', 'id')->where('is_active', true)]];
    }
}
