<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Symfony\Contract;

use Cognesy\Tell\Data\TellCommandDescriptors;

interface CanContributeTellCommands
{
    public function commands(): TellCommandDescriptors;
}
