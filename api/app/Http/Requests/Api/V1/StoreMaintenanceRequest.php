<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MaintenanceRequest::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'lease_id' => ['sometimes', 'nullable', 'integer', 'exists:leases,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'priority' => ['sometimes', 'required', Rule::in(MaintenanceRequest::PRIORITIES)],
            'cost' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    /**
     * A lease must be for the same property, on create and after a change of either.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $v): void {
            if ($v->errors()->hasAny(['lease_id', 'property_id'])) {
                return;
            }

            $current = $this->route('maintenance');
            $current = $current instanceof MaintenanceRequest ? $current : null;
            $lease = $this->has('lease_id') ? $this->input('lease_id') : $current?->lease_id;
            $property = $this->input('property_id', $current?->property_id);

            if ($lease && ! Lease::query()->whereKey($lease)->where('property_id', $property)->exists()) {
                $v->errors()->add('lease_id', 'This lease is for another property.');
            }
        }];
    }
}
