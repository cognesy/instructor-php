<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use Cognesy\Tell\Workspace\Branch\TellBranchConfig;

/** Immutable effective request intent and its non-secret provenance. */
final readonly class TellEffectiveConfiguration
{
    /** @param array<string, string> $provenance */
    public function __construct(
        public TellRequest $request,
        public array $provenance = [],
        public ?TellBranchConfig $branch = null,
    ) {}
}
