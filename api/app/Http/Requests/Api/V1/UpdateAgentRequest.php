<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\AgentRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentRequest extends FormRequest
{
    use AgentRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('agent')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->agentRules(false);
    }
}
