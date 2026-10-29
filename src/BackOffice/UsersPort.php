<?php
declare(strict_types=1);

namespace Dendrila\Cms\BackOffice;

use Dendrila\Cms\Authorization\ActorContext;

/** Adaptateur identité optionnel, toujours lié à la vraie 2FA du site. */
interface UsersPort
{
    /** @return list<array{label:string,email:string,role:string}> */
    public function list(ActorContext $actor): array;
    /** Invite sans mot de passe transmis ; le parcours sûr appartient au fournisseur d'identité. */
    public function invite(ActorContext $actor, string $email, string $role): void;
}
