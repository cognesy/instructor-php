<?php

declare(strict_types=1);

namespace Cognesy\Tell\Branch;

/** Safe, versioned branch-local Tell runtime intent. */
final readonly class TellBranchConfig
{
    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $provenance
     * @param  list<string>  $precedence
     * @param  array<string, mixed>|null  $connection
     */
    public function __construct(
        public string $branch,
        public int $version,
        public array $values,
        public array $provenance = [],
        public array $precedence = [],
        public ?array $connection = null,
    ) {}
}
