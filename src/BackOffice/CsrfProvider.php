<?php
declare(strict_types=1);

namespace Dendrila\Cms\BackOffice;

interface CsrfProvider
{
    public function token(): string;
    public function verify(string $submitted): bool;
}
