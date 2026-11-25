<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ViewingRules;
use App\Models\Viewing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreViewingRequest extends FormRequest
{
    use ViewingRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Viewing::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->viewingRules(true),
            // Without `viewings.view_all` the viewing always belongs to the user's own agent profile.
            'agent_id' => $this->user()?->can('viewings.view_all')
                ? ['sometimes', 'nullable', 'integer', Rule::exists('agents', 'id')->where('is_active', true)]
                : ['prohibited'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $v) => $this->checkViewingLength($v, $this->input('starts_at'), $this->input('ends_at'))];
    }
}
