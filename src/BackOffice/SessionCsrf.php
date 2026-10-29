<?php
declare(strict_types=1);

namespace Dendrila\Cms\BackOffice;

/** N'ouvre aucune session et n'authentifie personne : le site hôte le fait en amont. */
final class SessionCsrf implements CsrfProvider
{
    private const KEY = 'dendrila_backoffice_csrf';

    public function token(): string
    {
        $this->assertSession();
        $token=$_SESSION[self::KEY]??null;
        if (!is_string($token)||!preg_match('/^[0-9a-f]{64}$/D',$token)) {
            $token=bin2hex(random_bytes(32));
            $_SESSION[self::KEY]=$token;
        }
        return $token;
    }

    public function verify(string $submitted): bool
    {
        return $submitted!=='' && hash_equals($this->token(),$submitted);
    }

    private function assertSession(): void
    {
        if (session_status()!==PHP_SESSION_ACTIVE) {
            throw new \LogicException('Une session vérifiée par le site hôte est obligatoire.');
        }
    }
}
