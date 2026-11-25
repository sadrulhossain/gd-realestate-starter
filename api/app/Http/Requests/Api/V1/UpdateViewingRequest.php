<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ViewingRules;
use App\Models\Viewing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateViewingRequest extends FormRequest
{
    use ViewingRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('viewing')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->viewingRules(false);
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $v): void {
            /** @var Viewing $viewing */
            $viewing = $this->route('viewing');

            $this->checkViewingLength(
                $v,
                $this->input('starts_at', $viewing->starts_at),
                $this->input('ends_at', $viewing->ends_at),
            );
        }];
    }
}
