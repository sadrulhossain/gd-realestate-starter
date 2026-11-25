<?php

namespace App\Models;

use App\Models\Concerns\HasSecondLanguage;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A development (building or estate) with units that can be published as properties.
 *
 * @property Carbon|null $handover_date
 */
#[Fillable([
    'developer_id', 'location_id', 'name', 'name_2nd', 'slug', 'description', 'description_2nd', 'address_line',
    'lat', 'lng', 'status', 'handover_date', 'featured', 'is_active', 'seo_title', 'seo_description',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasSecondLanguage;

    public const STATUSES = ['upcoming', 'under_construction', 'completed'];

    protected static function booted(): void
    {
        static::creating(function (self $project): void {
            if (blank($project->slug)) {
                $project->slug = Str::slug($project->name);
            }
        });
    }

    /** @return BelongsTo<Developer, $this> */
    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<Property, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /** @return HasMany<ProjectUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ProjectUnit::class)->orderBy('code')->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'handover_date' => 'date',
            'featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'upcoming'];
}
