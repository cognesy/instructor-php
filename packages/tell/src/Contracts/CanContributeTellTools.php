<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Contracts\Collections\TellExtensionDescriptors;

interface CanContributeTellTools
{
    /** Every descriptor must have kind TellExtensionKind::Tool. */
    public function tools(): TellExtensionDescriptors;
}
