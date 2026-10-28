<?php
declare(strict_types=1);

namespace Dendrila\Cms\BackOffice;

final readonly class Result
{
    /** @param array<string,string> $headers */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers = [],
    ) {}
}
