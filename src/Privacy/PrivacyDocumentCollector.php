<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

use Dendrila\Cms\Authorization\ActorContext;

interface PrivacyDocumentCollector
{
    /**
     * @return list<array{id:string,url?:string,content:string}>
     */
    public function collect(ActorContext $actor, string $mode): array;
}
