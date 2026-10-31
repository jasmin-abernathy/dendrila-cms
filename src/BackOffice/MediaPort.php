<?php
declare(strict_types=1);

namespace Dendrila\Cms\BackOffice;

use Dendrila\Cms\Authorization\ActorContext;

/** Adaptateur média optionnel. Les contrôles MIME, EXIF, stockage et ACL restent côté serveur. */
interface MediaPort
{
    /** @return list<array{id:string,title:string,alt:string,preview_url:string}> */
    public function list(ActorContext $actor): array;

    /** @param array<string,mixed> $file */
    public function upload(ActorContext $actor, array $file, string $title, string $alt): void;
}
