<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Data\TellCommandDescriptors;

interface CanContributeTellCommands
{
    public function commands(): TellCommandDescriptors;
}
