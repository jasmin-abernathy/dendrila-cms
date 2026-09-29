<?php
declare(strict_types=1);

namespace LePotager\Cms\Api;

use LePotager\Cms\Authorization\ActorContext;
use LePotager\Cms\Http\Request;

interface DeviceAuthenticator
{
    /** Resolve the actor exclusively from a verified device token header. */
    public function authenticate(Request $request): ActorContext;
}
