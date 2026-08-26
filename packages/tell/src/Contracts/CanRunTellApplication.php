<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

interface CanRunTellApplication
{
    /** @param list<string> $arguments */
    public function run(array $arguments): int;
}
