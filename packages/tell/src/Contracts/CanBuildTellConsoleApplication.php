<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Data\TellCommandDescriptors;

interface CanBuildTellConsoleApplication
{
    public function build(TellCommandDescriptors $commands): CanRunTellConsoleApplication;
}
