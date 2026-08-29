<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use Cognesy\Tell\Shell\TellShellJobState;
use DateTimeImmutable;

final readonly class TellShellJobSnapshot
{
    public function __construct(
        public string $id,
        public TellShellJobState $state,
        public string $commandHash,
        public ?string $label,
        public string $workingDirectory,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt,
        public ?int $exitCode,
        public int $stdoutBytes,
        public int $stderrBytes,
        public bool $outputTruncated,
    ) {}

    public function isTerminal(): bool {
        return $this->state->isTerminal();
    }
}
