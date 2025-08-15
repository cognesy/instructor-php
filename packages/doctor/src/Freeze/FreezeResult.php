<?php

declare(strict_types=1);

namespace Cognesy\Doctor\Freeze;

class FreezeResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $output,
        public readonly string $errorOutput,
        public readonly string $command,
        public readonly ?string $outputPath = null,
    ) {}

    public function isSuccessful(): bool {
        return $this->success;
    }

    public function failed(): bool {
        return !$this->success;
    }

    public function getOutput(): string {
        return $this->output;
    }

    public function getErrorOutput(): string {
        return $this->errorOutput;
    }

    public function getCommand(): string {
        return $this->command;
    }

    public function getOutputPath(): ?string {
        return $this->outputPath;
    }

    public function hasOutputFile(): bool {
        return $this->outputPath !== null && file_exists($this->outputPath);
    }
}