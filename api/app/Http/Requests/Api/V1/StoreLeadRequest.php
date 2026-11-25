<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\LeadRules;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    use LeadRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->leadRules(true),
            // Only people who can assign leads choose the agent; everyone else's lead goes to their own profile.
            'agent_id' => $this->user()?->can('leads.assign')
                ? ['sometimes', 'nullable', 'integer', Rule::exists('agents', 'id')]
                : ['prohibited'],
        ];
    }
}
