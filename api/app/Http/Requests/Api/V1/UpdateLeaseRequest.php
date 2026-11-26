<?php

namespace App\Http\Requests\Api\V1;

/** The create rules, all optional. LeaseService refuses term changes once the lease is no longer a draft. */
class UpdateLeaseRequest extends StoreLeaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lease')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_map(
            fn (array $rules) => $rules[0] === 'sometimes' ? $rules : ['sometimes', ...$rules],
            parent::rules(),
        );
    }
}
