<?php
declare(strict_types=1);

namespace LePotager\Cms\Api;

final readonly class AuthenticationService
{
    public function __construct(
        private IdentityProvider $identityProvider,
        private DeviceTokenIssuer $tokenIssuer,
    ) {
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function start(array $input): array
    {
        $username = $this->requiredString($input, 'username', 254);
        $password = $this->requiredString($input, 'password', 1024, trimValue: false);
        $deviceName = $this->deviceName($input);
        $result = $this->identityProvider->start($username, $password, $deviceName);
        if ($result->needsChallenge()) {
            return [
                'status' => 'mfa_required',
                'challenge_id' => $result->challengeId,
                'methods' => $result->methods,
                'expires_in' => $result->expiresIn,
            ];
        }
        if ($result->login === null) {
            throw new \LogicException('Identity provider returned an incomplete authentication result.');
        }
        return ['status' => 'authenticated'] + $this->tokenIssuer->issue(
            $result->login->member,
            $result->login->deviceName,
        );
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function verify(array $input): array
    {
        $challengeId = $this->requiredString($input, 'challenge_id', 255);
        $method = $this->requiredString($input, 'method', 16);
        $code = $this->requiredString($input, 'code', 8);
        if (!preg_match('/^[A-Za-z0-9_-]{16,255}$/', $challengeId)
            || $method !== 'totp' || !preg_match('/^[0-9]{6}$/', $code)) {
            throw new AuthenticationFailure('Authentication failed.');
        }
        $login = $this->identityProvider->verify($challengeId, $method, $code);
        return ['status' => 'authenticated'] + $this->tokenIssuer->issue($login->member, $login->deviceName);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function pair(array $input): array
    {
        $code = $this->requiredString($input, 'code', 12);
        $deviceName = $this->deviceName($input);
        if (!preg_match('/^[0-9]{6,12}$/', $code)) {
            throw new AuthenticationFailure('Authentication failed.');
        }
        $member = $this->identityProvider->pair($code);
        return ['status' => 'authenticated'] + $this->tokenIssuer->issue($member, $deviceName);
    }

    /** @param array<string, mixed> $input */
    private function deviceName(array $input): string
    {
        $name = $this->requiredString($input, 'device_name', 120);
        if (preg_match('/[\x00-\x1f\x7f]/', $name)) {
            throw new \InvalidArgumentException('device_name contains unsupported characters.');
        }
        return $name;
    }

    /** @param array<string, mixed> $input */
    private function requiredString(array $input, string $key, int $maxLength, bool $trimValue = true): string
    {
        $value = $input[$key] ?? null;
        if (!is_string($value)) {
            throw new \InvalidArgumentException("{$key} must be a string.");
        }
        $value = $trimValue ? trim($value) : $value;
        if ($value === '' || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("{$key} must contain 1–{$maxLength} characters.");
        }
        return $value;
    }
}
