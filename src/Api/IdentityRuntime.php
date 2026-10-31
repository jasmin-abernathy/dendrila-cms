<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

final readonly class IdentityRuntime
{
    public function __construct(
        public DeviceAuthenticator $authenticator,
        public ?AuthenticationService $authentication,
    ) {
    }
}
