<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PropertyStatus;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Query string of GET public/properties. Anonymous. */
class PublicPropertyIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** `amenities=1,2,3` is accepted next to `amenities[]=1`. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->query('amenities'))) {
            $this->merge(['amenities' => array_filter(explode(',', $this->query('amenities')), fn (string $id): bool => $id !== '')]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'listing_type' => [Rule::in(Property::LISTING_TYPES)],
            'property_type' => ['string', 'max:60'],
            'location' => ['integer', 'min:1'],
            'price_min' => ['integer', 'min:0'],
            'price_max' => ['integer', 'min:0', ...($this->has('price_min') ? ['gte:price_min'] : [])],
            'beds_min' => ['integer', 'min:0', 'max:99'],
            'baths_min' => ['integer', 'min:0', 'max:99'],
            'area_min' => ['numeric', 'min:0'],
            'area_max' => ['numeric', 'min:0', ...($this->has('area_min') ? ['gte:area_min'] : [])],
            'amenities' => ['array', 'max:20'],
            'amenities.*' => ['integer', 'min:1'],
            'furnished' => [Rule::in(Property::FURNISHED)],
            'featured' => ['boolean'],
            'status' => [Rule::in(PropertyStatus::publicValues())],
            'q' => ['string', 'max:100'],
            'agent' => ['integer', 'min:1'],
            'project' => ['integer', 'min:1'],
            'slugs' => ['array', 'max:48'],
            'slugs.*' => ['string', 'max:160'],
            'lat' => ['numeric', 'between:-90,90', 'required_with:lng,radius_km'],
            'lng' => ['numeric', 'between:-180,180', 'required_with:lat,radius_km'],
            'radius_km' => ['numeric', 'gt:0', 'max:500', 'required_with:lat,lng'],
            'sort' => [Rule::in(['relevance', 'newest', 'price_asc', 'price_desc', 'area_asc', 'area_desc'])],
            'per_page' => ['integer', 'min:1', 'max:48'],
            'page' => ['integer', 'min:1'],
        ];
    }
}
