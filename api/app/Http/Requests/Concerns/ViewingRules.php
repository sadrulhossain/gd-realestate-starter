<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Field rules shared by the viewing create and update requests. Status never comes through here. */
trait ViewingRules
{
    public const MAX_MINUTES = 480;

    /** @return array<string, mixed> */
    protected function viewingRules(bool $creating): array
    {
        $req = $creating ? ['required'] : ['sometimes', 'required'];

        return [
            'property_id' => [...($creating ? ['required'] : ['prohibited']), 'integer', Rule::exists('properties', 'id')],
            'lead_id' => ['sometimes', 'nullable', 'integer', Rule::exists('leads', 'id')],
            'starts_at' => [...$req, 'date', ...($creating ? ['after:now'] : [])],
            'ends_at' => [...$req, 'date', 'after:starts_at'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
        ];
    }

    /** A viewing is at most eight hours long. */
    protected function checkViewingLength(Validator $validator, mixed $start, mixed $end): void
    {
        if ($validator->errors()->isNotEmpty() || blank($start) || blank($end)) {
            return;
        }

        if (abs(strtotime((string) $end) - strtotime((string) $start)) > self::MAX_MINUTES * 60) {
            $validator->errors()->add('ends_at', 'A viewing lasts at most '.(self::MAX_MINUTES / 60).' hours.');
        }
    }
}
