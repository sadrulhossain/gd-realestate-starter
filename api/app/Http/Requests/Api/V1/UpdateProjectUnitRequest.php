<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ProjectUnitRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectUnitRequest extends FormRequest
{
    use ProjectUnitRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('unit')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->unitRules(false, $this->route('unit')?->project_id, $this->route('unit')?->id);
    }
}
