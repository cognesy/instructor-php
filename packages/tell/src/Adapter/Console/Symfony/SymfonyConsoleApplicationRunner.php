<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Symfony;

use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanRunTellConsoleApplication;

final readonly class SymfonyConsoleApplicationRunner implements CanRunTellConsoleApplication
{
    public function __construct(private TellConsoleApplication $application) {}

    #[\Override]
    public function run(array $arguments): int {
        return $this->application->runArgv($arguments);
    }
}
