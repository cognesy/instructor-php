<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Nodes;

use Cognesy\Utils\ProgrammingLanguage;

final readonly class CodeBlockNode extends Node
{
    public int $linesOfCode;

    public function __construct(
        public string $id,
        public string $language,
        public string $content,  // This is now the clean content without PHP tags
        public array $metadata = [],
        public bool $hasPhpOpenTag = false,
        public bool $hasPhpCloseTag = false,
        public string $originalContent = '',  // Original content with PHP tags if needed
        int $lineNumber = 0,
    ) {
        parent::__construct($lineNumber);
        $this->linesOfCode = ProgrammingLanguage::linesOfCode($this->language, $this->content);
    }

    public function getContentWithoutPhpTags(): string {
        return $this->content;  // Content is already clean
    }

    public function hasPhpTags(): bool {
        return $this->hasPhpOpenTag || $this->hasPhpCloseTag;
    }

    public function hasMetadata(string $key): bool {
        return array_key_exists($key, $this->metadata);
    }

    public function metadata(string $key, mixed $default = null): mixed {
        return $this->metadata[$key] ?? $default;
    }

    public function withContent(string $content): self {
        return new self(
            id: $this->id,
            language: $this->language,
            content: $content,
            metadata: $this->metadata,
            hasPhpOpenTag: $this->hasPhpOpenTag,
            hasPhpCloseTag: $this->hasPhpCloseTag,
            originalContent: $this->originalContent,
            lineNumber: $this->lineNumber,
        );
    }

    public function withMetadata(array $metadata): self {
        return new self(
            id: $this->id,
            language: $this->language,
            content: $this->content,
            metadata: $metadata,
            hasPhpOpenTag: $this->hasPhpOpenTag,
            hasPhpCloseTag: $this->hasPhpCloseTag,
            originalContent: $this->originalContent,
            lineNumber: $this->lineNumber,
        );
    }
}
