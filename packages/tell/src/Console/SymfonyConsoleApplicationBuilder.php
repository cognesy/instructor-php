<?php

declare(strict_types=1);

namespace Cognesy\Tell\Console;

use Cognesy\Tell\Contracts\CanBuildTellConsoleApplication;
use Cognesy\Tell\Contracts\CanRunTellConsoleApplication;
use Cognesy\Tell\Data\TellCommandDescriptors;

final readonly class SymfonyConsoleApplicationBuilder implements CanBuildTellConsoleApplication
{
    #[\Override]
    public function build(TellCommandDescriptors $commands): CanRunTellConsoleApplication {
        $application = TellConsoleApplication::fromDescriptors($commands);
        $application->setAutoExit(false);

        return new SymfonyConsoleApplicationRunner($application);
    }
}
