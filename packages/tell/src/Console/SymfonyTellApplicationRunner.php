<?php

declare(strict_types=1);

namespace Cognesy\Tell\Console;

use Cognesy\Tell\Contracts\CanRunTellApplication;
use Cognesy\Tell\TellApplication;

final readonly class SymfonyTellApplicationRunner implements CanRunTellApplication
{
    public function __construct(private TellApplication $application) {}

    public function run(array $arguments): int
    {
        return $this->application->runArgv($arguments);
    }
}
