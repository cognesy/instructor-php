<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Contracts\Collections\TellCommandDescriptors;

interface CanBuildTellApplication
{
    public function build(TellCommandDescriptors $commands): CanRunTellApplication;
}
