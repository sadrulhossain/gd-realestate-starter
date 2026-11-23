<?php

namespace App\Models;

use App\Models\Concerns\HasSecondLanguage;
use Database\Factories\ProjectUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** One sellable unit of a project. Price is in minor units. */
#[Fillable(['project_id', 'property_type_id', 'code', 'name', 'name_2nd', 'beds', 'baths', 'area', 'area_unit', 'floor', 'price', 'status'])]
class ProjectUnit extends Model
{
    /** @use HasFactory<ProjectUnitFactory> */
    use HasFactory;

    use HasSecondLanguage;

    public const STATUSES = ['available', 'reserved', 'sold'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<PropertyType, $this> */
    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    /** @return HasOne<Property, $this> */
    public function property(): HasOne
    {
        return $this->hasOne(Property::class, 'unit_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['price' => 'integer', 'area' => 'float', 'beds' => 'integer', 'baths' => 'integer', 'floor' => 'integer'];
    }

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'available', 'area_unit' => 'sqft'];
}
