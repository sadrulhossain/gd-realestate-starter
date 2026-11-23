<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ProjectRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    use ProjectRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->projectRules(false);
    }
}
