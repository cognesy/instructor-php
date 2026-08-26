<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Contracts\Data\TellExtensionCatalogue;

interface CanCatalogueTellExtensions
{
    public function catalogue(string $directory): TellExtensionCatalogue;
}
