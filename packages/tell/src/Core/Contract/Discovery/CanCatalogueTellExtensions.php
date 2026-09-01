<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Discovery;

use Cognesy\Tell\Data\TellExtensionCatalogue;

interface CanCatalogueTellExtensions
{
    public function catalogue(string $directory): TellExtensionCatalogue;
}
