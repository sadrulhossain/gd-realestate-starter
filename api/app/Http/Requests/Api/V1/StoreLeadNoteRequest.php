<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lead')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }
}
