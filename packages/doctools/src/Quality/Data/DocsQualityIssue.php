<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Data;

final readonly class DocsQualityIssue
{
    public function __construct(
        public string $filePath,
        public ?int $line,
        public string $message,
    ) {}

    public function format(): string
    {
        if ($this->line === null) {
            return "{$this->filePath}: {$this->message}";
        }

        return "{$this->filePath}:{$this->line} {$this->message}";
    }
}
