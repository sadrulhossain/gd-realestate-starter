<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesLocationTree;
use App\Support\Bilingual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLocationRequest extends FormRequest
{
    use ValidatesLocationTree;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('location')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...Bilingual::rules(['name' => ['sometimes', 'required', 'string', 'max:100']]),
            ...$this->locationRules(false),
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkLocationTree($validator, $this->route('location'))];
    }
}
