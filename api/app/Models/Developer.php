<?php

namespace App\Models;

use App\Models\Concerns\HasSecondLanguage;
use Database\Factories\DeveloperFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A property developer that owns projects. */
#[Fillable(['name', 'name_2nd', 'slug', 'description', 'description_2nd', 'website', 'email', 'phone', 'sort_order', 'is_active'])]
class Developer extends Model
{
    /** @use HasFactory<DeveloperFactory> */
    use HasFactory;

    use HasSecondLanguage;

    protected static function booted(): void
    {
        static::creating(function (self $developer): void {
            if (blank($developer->slug)) {
                $developer->slug = Str::slug($developer->name);
            }
        });
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
