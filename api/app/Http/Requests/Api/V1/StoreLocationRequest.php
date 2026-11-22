<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesLocationTree;
use App\Models\Location;
use App\Support\Bilingual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLocationRequest extends FormRequest
{
    use ValidatesLocationTree;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Location::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...Bilingual::rules(['name' => ['required', 'string', 'max:100']]),
            ...$this->locationRules(true),
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkLocationTree($validator, null)];
    }
}
