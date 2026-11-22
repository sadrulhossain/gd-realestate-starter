<?php

namespace App\Models;

use App\Models\Concerns\HasSecondLanguage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A feature a property can have (lift, parking, pool...). */
#[Fillable(['name', 'name_2nd', 'icon', 'group', 'sort_order', 'is_active'])]
class Amenity extends Model
{
    use HasSecondLanguage;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
