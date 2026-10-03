<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

use Dendrila\Cms\Authorization\ActorContext;

final readonly class PrivacyScanRunner
{
    public function __construct(
        private PrivacyService $privacy,
        private PrivacyInspector $inspector,
        private PrivacyDocumentCollector $collector,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(ActorContext $actor, string $mode): array
    {
        if (!in_array($mode, ['standard', 'full'], true)) {
            throw new \InvalidArgumentException('Unknown privacy scan mode.');
        }
        $documents = $this->collector->collect($actor, $mode);
        return $this->privacy->recordCompletedScan(
            $actor,
            $mode,
            $this->inspector->inspect($documents),
        );
    }
}
