<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts\Data;

use Cognesy\Tell\Contracts\Collections\TellExtensionDescriptors;
use Cognesy\Tell\Diagnostics\TellDiagnostic;

final readonly class TellExtensionCatalogue
{
    /** @param list<TellDiagnostic> $diagnostics */
    public function __construct(
        public TellExtensionDescriptors $accepted = new TellExtensionDescriptors,
        public array $diagnostics = [],
    ) {}
}
