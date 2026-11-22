<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('property')) ?? false;
    }

    /**
     * `is_cover` can only be set to true: make another image the cover instead of clearing this one.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sort' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_cover' => ['sometimes', 'accepted'],
        ];
    }
}
