<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\DeveloperRules;
use App\Models\Developer;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeveloperRequest extends FormRequest
{
    use DeveloperRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Developer::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->developerRules(true);
    }
}
