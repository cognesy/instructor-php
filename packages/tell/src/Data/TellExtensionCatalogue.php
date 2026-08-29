<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

final readonly class TellExtensionCatalogue
{
    /** @param list<TellDiagnostic> $diagnostics */
    public function __construct(
        public TellExtensionDescriptors $accepted = new TellExtensionDescriptors(),
        public array $diagnostics = [],
    ) {}
}
