<?php

namespace App\Models;

use App\Models\Concerns\HasSecondLanguage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A kind of property (apartment, plot, shop...) inside one group: residential, commercial or land. */
#[Fillable(['key', 'name', 'name_2nd', 'group', 'sort_order', 'is_active'])]
class PropertyType extends Model
{
    use HasSecondLanguage;

    public const GROUPS = ['residential', 'commercial', 'land'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
