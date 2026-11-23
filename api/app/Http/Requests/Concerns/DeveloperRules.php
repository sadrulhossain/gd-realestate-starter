<?php

namespace App\Http\Requests\Concerns;

use App\Support\Bilingual;
use Illuminate\Validation\Rule;

/** Field rules shared by the developer create and update requests. */
trait DeveloperRules
{
    /** @return array<string, mixed> */
    protected function developerRules(bool $creating): array
    {
        $req = $creating ? ['required'] : ['sometimes', 'required'];
        $opt = ['sometimes', 'nullable'];

        return [
            ...Bilingual::rules([
                'name' => [...$req, 'string', 'max:150'],
                'description' => [...$opt, 'string', 'max:5000'],
            ]),
            'slug' => ['sometimes', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('developers', 'slug')->ignore($this->route('developer'))],
            'website' => [...$opt, 'url:http,https', 'max:255'],
            'email' => [...$opt, 'email', 'max:255'],
            'phone' => [...$opt, 'string', 'max:40'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
