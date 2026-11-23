<?php

namespace App\Http\Resources\Api\V1;

/** Visitor view of a unit: no linked property, no timestamps. */
class PublicProjectUnitResource extends ProjectUnitResource
{
    protected bool $public = true;
}
