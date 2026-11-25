<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Models\Concerns\HasSecondLanguage;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A sales agent, optionally linked to a staff user (who then sees only their own leads and viewings). */
#[Fillable(['user_id', 'name', 'slug', 'title', 'title_2nd', 'bio', 'bio_2nd', 'email', 'phone', 'whatsapp', 'license_no', 'image', 'sort_order', 'is_active'])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory;

    use HasSecondLanguage;

    protected static function booted(): void
    {
        static::creating(function (self $agent): void {
            if (blank($agent->slug)) {
                $base = Str::slug($agent->name) ?: 'agent';
                $slug = $base;

                for ($i = 2; self::query()->where('slug', $slug)->exists(); $i++) {
                    $slug = $base.'-'.$i;
                }

                $agent->slug = $slug;
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Property, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Listings a visitor can see (available or under offer).
     *
     * @return HasMany<Property, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Property::class)->whereIn('status', PropertyStatus::publicValues());
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return HasMany<Viewing, $this> */
    public function viewings(): HasMany
    {
        return $this->hasMany(Viewing::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
