<?php

declare(strict_types=1);

namespace Cognesy\Tell\Console;

use Cognesy\Tell\Contracts\CanBuildTellApplication;
use Cognesy\Tell\Contracts\CanRunTellApplication;
use Cognesy\Tell\Data\TellCommandDescriptors;

final readonly class SymfonyTellApplicationBuilder implements CanBuildTellApplication
{
    public function build(TellCommandDescriptors $commands): CanRunTellApplication {
        $application = TellApplication::fromDescriptors($commands);
        $application->setAutoExit(false);

        return new SymfonyTellApplicationRunner($application);
    }
}
