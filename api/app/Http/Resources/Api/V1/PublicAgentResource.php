<?php

namespace App\Http\Resources\Api\V1;

/** Visitor view of an agent: contact details meant for enquiries, no user link or admin fields. */
class PublicAgentResource extends AgentResource
{
    protected bool $public = true;
}
