<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Data;

readonly class GenerationResult
{
    public function __construct(
        public bool $success,
        public int $filesProcessed = 0,
        public int $filesSkipped = 0,
        public int $filesCreated = 0,
        public int $filesUpdated = 0,
        public array $errors = [],
        public float $duration = 0.0,
        public string $message = ''
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getTotalFiles(): int
    {
        return $this->filesProcessed + $this->filesSkipped;
    }

    public static function success(
        int $filesProcessed = 0,
        int $filesSkipped = 0,
        int $filesCreated = 0,
        int $filesUpdated = 0,
        float $duration = 0.0,
        string $message = ''
    ): self {
        return new self(
            success: true,
            filesProcessed: $filesProcessed,
            filesSkipped: $filesSkipped,
            filesCreated: $filesCreated,
            filesUpdated: $filesUpdated,
            duration: $duration,
            message: $message
        );
    }

    public static function failure(
        array $errors = [],
        int $filesProcessed = 0,
        float $duration = 0.0,
        string $message = ''
    ): self {
        return new self(
            success: false,
            filesProcessed: $filesProcessed,
            errors: $errors,
            duration: $duration,
            message: $message
        );
    }
}