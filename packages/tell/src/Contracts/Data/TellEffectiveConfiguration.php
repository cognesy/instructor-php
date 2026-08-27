<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts\Data;

use Cognesy\Tell\Branch\TellBranchConfig;
use Cognesy\Tell\TellRequest;

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
