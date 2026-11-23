<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\DeveloperRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeveloperRequest extends FormRequest
{
    use DeveloperRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('developer')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->developerRules(false);
    }
}
