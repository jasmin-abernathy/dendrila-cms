<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use PDO;

final class IdentityRuntimeFactory
{
    /** @param null|callable(string):(?string) $env */
    public static function fromEnvironment(PDO $pdo, ?callable $env = null): IdentityRuntime
    {
        $env ??= static function (string $key): ?string {
            $value = getenv($key);
            return $value === false ? null : $value;
        };

        $provider = trim((string) ($env('DENDRILA_IDENTITY_PROVIDER') ?? ''));
        if ($provider === '' || $provider === 'none') {
            return new IdentityRuntime(new PdoDeviceAuthenticator($pdo), null);
        }
        if ($provider === 'native') {
            $siteId=trim((string)($env('DENDRILA_SITE_ID')??''));
            $key=(string)($env('DENDRILA_AUTH_KEY')??'');
            $pairing=new NativeMmwPairing($pdo,$siteId,$key);
            return new IdentityRuntime(
                new NativeMmwDeviceAuthenticator($pdo,$siteId),
                new AuthenticationService($pairing,new PdoDeviceTokenIssuer($pdo)),
            );
        }
        if ($provider !== 'legacy_mmw_pairing') {
            throw new \RuntimeException('Unsupported identity provider configuration.');
        }

        $siteId = trim((string) ($env('DENDRILA_IDENTITY_SITE_ID') ?? ''));
        $secret = (string) ($env('DENDRILA_IDENTITY_HMAC_SECRET') ?? '');
        $roleMapJson = (string) ($env('DENDRILA_IDENTITY_ROLE_MAP') ?? '');
        try {
            $roleMap = json_decode($roleMapJson, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException('DENDRILA_IDENTITY_ROLE_MAP must be a JSON object.', previous: $error);
        }
        if (!is_array($roleMap) || array_is_list($roleMap) || $roleMap === []) {
            throw new \RuntimeException('DENDRILA_IDENTITY_ROLE_MAP must be a non-empty JSON object.');
        }

        $identity = new LegacyMmwPairingIdentityProvider($pdo, $siteId, $secret, $roleMap);
        $issuer = new LegacyMmwDeviceTokenIssuer($pdo, $siteId, $secret);
        return new IdentityRuntime(
            new LegacyMmwDeviceAuthenticator($pdo, $siteId, $secret),
            new AuthenticationService($identity, $issuer),
        );
    }
}
