<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\LeadRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{
    use LeadRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lead')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->leadRules(false);
    }
}
