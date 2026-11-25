<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Models\Concerns\HasSecondLanguage;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A property for sale or rent. `status`, `ref_code` and `published_at` are not fillable:
 * the status moves only through PropertyStatusService. Price is in minor units.
 *
 * @property PropertyStatus $status
 */
#[Fillable([
    'slug', 'title', 'title_2nd', 'description', 'description_2nd', 'listing_type', 'property_type_id', 'location_id',
    'address_line', 'lat', 'lng', 'price', 'rent_period', 'area', 'area_unit', 'beds', 'baths', 'floor', 'total_floors',
    'facing', 'year_built', 'furnished', 'featured', 'agent_id', 'project_id', 'unit_id', 'owner_id',
    'seo_title', 'seo_description',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    use HasSecondLanguage;

    public const LISTING_TYPES = ['sale', 'rent'];

    public const RENT_PERIODS = ['month', 'year', 'day'];

    public const AREA_UNITS = ['sqft', 'sqm'];

    public const FURNISHED = ['unfurnished', 'semi_furnished', 'furnished'];

    public const FACINGS = ['north', 'south', 'east', 'west', 'north_east', 'north_west', 'south_east', 'south_west'];

    protected static function booted(): void
    {
        static::creating(function (self $property): void {
            $number = (int) DB::scalar("select nextval('property_ref_seq')");
            $property->ref_code = 'P-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);

            if (blank($property->slug)) {
                $property->slug = Str::slug($property->title.' '.$property->ref_code);
            }
        });

        // A sale listing has no rent period.
        static::saving(function (self $property): void {
            if ($property->listing_type === 'sale') {
                $property->rent_period = null;
            }
        });

        static::created(function (self $property): void {
            $property->statusHistory()->create(['from_status' => null, 'to_status' => PropertyStatus::Draft->value, 'user_id' => auth('api')->id()]);
        });
    }

    /**
     * Visible to visitors: available or under offer.
     *
     * @param  Builder<Property>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->whereIn('status', PropertyStatus::publicValues());
    }

    /** @return BelongsTo<PropertyType, $this> */
    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return BelongsToMany<Amenity, $this> */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_amenities')->orderBy('amenities.sort_order')->orderBy('amenities.id');
    }

    /** @return HasMany<PropertyMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(PropertyMedia::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasOne<PropertyMedia, $this> */
    public function coverMedia(): HasOne
    {
        return $this->hasOne(PropertyMedia::class)->where('is_cover', true);
    }

    /** @return HasMany<PropertyStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(PropertyStatusHistory::class)->orderByDesc('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PropertyStatus::class,
            'price' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'area' => 'float',
            'featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** The generated search column is never loaded into models. */
    protected $hidden = ['search_vector'];

    /** @var array<string, mixed> New models start as drafts (matches the column default). */
    protected $attributes = ['status' => 'draft'];
}
