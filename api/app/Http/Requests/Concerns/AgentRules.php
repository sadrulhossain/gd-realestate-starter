<?php

namespace App\Http\Requests\Concerns;

use App\Support\Bilingual;
use Illuminate\Validation\Rule;

/** Field rules shared by the agent create and update requests. */
trait AgentRules
{
    /** @return array<string, mixed> */
    protected function agentRules(bool $creating): array
    {
        $req = $creating ? ['required'] : ['sometimes', 'required'];
        $opt = ['sometimes', 'nullable'];

        return [
            ...Bilingual::rules([
                'title' => [...$opt, 'string', 'max:150'],
                'bio' => [...$opt, 'string', 'max:5000'],
            ]),
            'name' => [...$req, 'string', 'max:150'],
            'slug' => ['sometimes', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('agents', 'slug')->ignore($this->route('agent'))],
            'user_id' => [...$opt, 'integer', Rule::exists('users', 'id'), Rule::unique('agents', 'user_id')->ignore($this->route('agent'))],
            'email' => [...$opt, 'email', 'max:150'],
            'phone' => [...$opt, 'string', 'max:40'],
            'whatsapp' => [...$opt, 'string', 'max:40'],
            'license_no' => [...$opt, 'string', 'max:60'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
