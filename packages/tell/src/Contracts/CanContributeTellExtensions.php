<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Data\TellExtensionDescriptors;

interface CanContributeTellExtensions
{
    public function extensions(): TellExtensionDescriptors;
}
