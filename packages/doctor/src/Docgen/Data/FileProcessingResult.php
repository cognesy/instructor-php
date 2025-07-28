<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Data;

readonly class FileProcessingResult
{
    public function __construct(
        public string $filePath,
        public bool $success,
        public string $action, // 'created', 'updated', 'skipped', 'error'
        public string $message = '',
        public ?\Throwable $error = null,
    ) {}

    public static function created(string $filePath, string $message = ''): self {
        return new self($filePath, true, 'created', $message);
    }

    public static function updated(string $filePath, string $message = ''): self {
        return new self($filePath, true, 'updated', $message);
    }

    public static function skipped(string $filePath, string $message = ''): self {
        return new self($filePath, true, 'skipped', $message);
    }

    public static function error(string $filePath, string $message = '', ?\Throwable $error = null): self {
        return new self($filePath, false, 'error', $message, $error);
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function wasProcessed(): bool {
        return in_array($this->action, ['created', 'updated']);
    }
}