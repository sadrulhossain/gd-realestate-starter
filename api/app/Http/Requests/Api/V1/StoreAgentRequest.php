<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\AgentRules;
use App\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
{
    use AgentRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Agent::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->agentRules(true);
    }
}
