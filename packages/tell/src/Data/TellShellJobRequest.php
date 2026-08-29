<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use InvalidArgumentException;

final readonly class TellShellJobRequest
{
    public function __construct(
        public string $command,
        public ?string $workingDirectory = null,
        public ?int $timeoutMs = null,
        public ?int $maxOutputBytes = null,
        public ?string $label = null,
    ) {
        if (trim($this->command) === '') {
            throw new InvalidArgumentException('A shell job command cannot be empty.');
        }
        if ($this->timeoutMs !== null && $this->timeoutMs < 1) {
            throw new InvalidArgumentException('A shell job timeout must be positive.');
        }
        if ($this->maxOutputBytes !== null && $this->maxOutputBytes < 1) {
            throw new InvalidArgumentException('A shell job output bound must be positive.');
        }
        if ($this->label !== null && trim($this->label) === '') {
            throw new InvalidArgumentException('A shell job label cannot be blank.');
        }
    }

    public static function command(string $command): self {
        return new self($command);
    }

    public function in(string $workingDirectory): self {
        return new self($this->command, $workingDirectory, $this->timeoutMs, $this->maxOutputBytes, $this->label);
    }

    public function forMilliseconds(int $timeoutMs): self {
        return new self($this->command, $this->workingDirectory, $timeoutMs, $this->maxOutputBytes, $this->label);
    }

    public function retaining(int $maxOutputBytes): self {
        return new self($this->command, $this->workingDirectory, $this->timeoutMs, $maxOutputBytes, $this->label);
    }

    public function named(string $label): self {
        return new self($this->command, $this->workingDirectory, $this->timeoutMs, $this->maxOutputBytes, $label);
    }
}
