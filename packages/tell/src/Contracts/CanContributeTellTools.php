<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Data\TellExtensionDescriptors;
use Cognesy\Tell\Data\TellExtensionKind;

interface CanContributeTellTools
{
    /** Every descriptor must have kind TellExtensionKind::Tool. */
    public function tools(): TellExtensionDescriptors;
}
