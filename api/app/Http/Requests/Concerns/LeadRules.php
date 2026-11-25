<?php

namespace App\Http\Requests\Concerns;

use App\Models\Lead;
use Illuminate\Validation\Rule;

/** Field rules shared by the admin lead requests. Status and agent never come through here. */
trait LeadRules
{
    /** @return array<string, mixed> */
    protected function leadRules(bool $creating): array
    {
        $req = $creating ? ['required'] : ['sometimes', 'required'];
        $opt = ['sometimes', 'nullable'];

        return [
            'name' => [...$req, 'string', 'max:100'],
            'email' => [...$opt, 'email', 'max:150', ...($creating ? ['required_without:phone'] : [])],
            'phone' => [...$opt, 'string', 'max:30', ...($creating ? ['required_without:email'] : [])],
            'message' => [...$opt, 'string', 'max:5000'],
            'source' => ['sometimes', Rule::in(Lead::SOURCES)],
            'property_id' => [...$opt, 'integer', Rule::exists('properties', 'id')],
            'project_id' => [...$opt, 'integer', Rule::exists('projects', 'id')],
            'status' => ['prohibited'],
            'agent_id' => ['prohibited'],
        ];
    }
}
