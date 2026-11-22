<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PropertyMedia;
use App\Services\MediaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('property')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $kind = $this->input('kind');

        return [
            'kind' => ['required', Rule::in(PropertyMedia::KINDS)],
            'file' => $kind === 'video_url'
                ? ['prohibited']
                : ['required', ...($kind === 'brochure' ? MediaService::documentRules() : MediaService::imageRules())],
            'url' => $kind === 'video_url' ? ['required', 'url:http,https', 'max:500'] : ['prohibited'],
        ];
    }
}
