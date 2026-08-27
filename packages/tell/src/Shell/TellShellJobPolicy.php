<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

use InvalidArgumentException;

final readonly class TellShellJobPolicy
{
    public function __construct(
        public int $maxConcurrentJobs = 4,
        public int $maxLifetimeMs = 120_000,
        public int $maxRetainedOutputBytes = 131_072,
        public int $maxReadBytes = 32_768,
        public int $cancellationGraceMs = 500,
    ) {
        foreach ([
            'maxConcurrentJobs' => $this->maxConcurrentJobs,
            'maxLifetimeMs' => $this->maxLifetimeMs,
            'maxRetainedOutputBytes' => $this->maxRetainedOutputBytes,
            'maxReadBytes' => $this->maxReadBytes,
            'cancellationGraceMs' => $this->cancellationGraceMs,
        ] as $name => $value) {
            if ($value < 1) {
                throw new InvalidArgumentException("Shell job policy {$name} must be positive.");
            }
        }
    }
}
