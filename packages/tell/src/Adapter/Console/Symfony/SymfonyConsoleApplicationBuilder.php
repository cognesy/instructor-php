<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Symfony;

use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanBuildTellConsoleApplication;
use Cognesy\Tell\Adapter\Console\Symfony\Contract\CanRunTellConsoleApplication;
use Cognesy\Tell\Data\TellCommandDescriptors;

final readonly class SymfonyConsoleApplicationBuilder implements CanBuildTellConsoleApplication
{
    #[\Override]
    public function build(TellCommandDescriptors $commands): CanRunTellConsoleApplication {
        $application = new TellConsoleApplication($commands);
        $application->setAutoExit(false);

        return new SymfonyConsoleApplicationRunner($application);
    }
}
