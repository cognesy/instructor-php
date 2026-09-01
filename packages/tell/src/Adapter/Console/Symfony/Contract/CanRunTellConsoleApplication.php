<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Symfony\Contract;

interface CanRunTellConsoleApplication
{
    /** @param list<string> $arguments */
    public function run(array $arguments): int;
}
