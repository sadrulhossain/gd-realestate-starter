<?php

namespace App\Http\Resources\Api\V1;

use App\Models\RentCharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RentCharge */
class RentChargeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lease_id' => $this->lease_id,
            'lease' => $this->whenLoaded('lease', fn () => [
                'id' => $this->lease->id,
                'property' => ['id' => $this->lease->property->id, 'ref_code' => $this->lease->property->ref_code, 'title' => $this->lease->property->title],
                'tenant' => ['id' => $this->lease->tenant->id, 'name' => $this->lease->tenant->name],
            ]),
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'status' => $this->status,
            'overdue' => $this->isOverdue(),
            'note' => $this->note,
            'payments' => RentPaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
