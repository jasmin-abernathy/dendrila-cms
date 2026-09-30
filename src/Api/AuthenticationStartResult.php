<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

final readonly class AuthenticationStartResult
{
    /** @param list<string> $methods */
    private function __construct(
        public ?string $challengeId,
        public array $methods,
        public ?int $expiresIn,
        public ?VerifiedLogin $login,
    ) {
    }

    /** @param list<string> $methods */
    public static function challenge(string $challengeId, array $methods, int $expiresIn): self
    {
        if (!preg_match('/^[A-Za-z0-9_-]{16,255}$/', $challengeId)
            || $methods !== ['totp'] || $expiresIn < 1 || $expiresIn > 300) {
            throw new \InvalidArgumentException('Authentication challenge is not valid for protocol v1.');
        }
        return new self($challengeId, $methods, $expiresIn, null);
    }

    public static function authenticated(VerifiedLogin $login): self
    {
        return new self(null, [], null, $login);
    }

    public function needsChallenge(): bool
    {
        return $this->challengeId !== null;
    }
}
