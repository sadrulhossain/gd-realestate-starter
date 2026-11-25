<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ProjectRules;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    use ProjectRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->projectRules(true);
    }
}
