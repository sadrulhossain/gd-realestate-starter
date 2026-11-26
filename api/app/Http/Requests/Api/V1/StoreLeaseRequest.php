<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Lease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaseRequest extends FormRequest
{
    /** Longest lease: keeps a schedule to a sensible number of rows. */
    private const MAX_YEARS = 10;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Lease::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')->where('listing_type', 'rent')],
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'rent_amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'rent_period' => ['sometimes', 'required', Rule::in(Lease::RENT_PERIODS)],
            'deposit_amount' => ['sometimes', 'required', 'integer', 'min:0', 'max:999999999999'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $v): void {
            if ($v->errors()->hasAny(['start_date', 'end_date'])) {
                return;
            }

            $lease = $this->route('lease');
            $lease = $lease instanceof Lease ? $lease : null;
            $start = $this->input('start_date', $lease?->start_date->toDateString());
            $end = $this->input('end_date', $lease?->end_date->toDateString());

            if (! $start || ! $end) {
                return;
            }

            if ($end <= $start) {
                $v->errors()->add('end_date', 'The end date must be after the start date.');
            } elseif ($end > Carbon::parse($start)->addYears(self::MAX_YEARS)->toDateString()) {
                $v->errors()->add('end_date', 'A lease can run for at most '.self::MAX_YEARS.' years.');
            }
        }];
    }
}
