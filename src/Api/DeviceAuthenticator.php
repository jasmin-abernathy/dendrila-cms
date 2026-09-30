<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Http\Request;

interface DeviceAuthenticator
{
    /** Resolve the actor exclusively from a verified device token header. */
    public function authenticate(Request $request): ActorContext;
}
