<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ProjectUnitRules;
use App\Models\ProjectUnit;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectUnitRequest extends FormRequest
{
    use ProjectUnitRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ProjectUnit::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->unitRules(true, $this->route('project')?->id, null);
    }
}
