<?php

namespace App\Http\Resources\Api\V1;

/** Visitor view of a project: public developer fields, public units, no `is_active` or timestamps. */
class PublicProjectResource extends ProjectResource
{
    protected bool $public = true;
}
