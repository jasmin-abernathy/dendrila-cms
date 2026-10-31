<?php
declare(strict_types=1);

namespace Dendrila\Cms\BackOffice;

use Dendrila\Cms\Authorization\ActorContext;

/** Le site hôte valide le mot de passe, sa 2FA et sa session AVANT de fournir un acteur. */
interface IdentityProvider
{
    public function currentActor(): ?ActorContext;
}
