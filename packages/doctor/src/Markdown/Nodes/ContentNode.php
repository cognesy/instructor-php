<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Nodes;

final readonly class ContentNode extends Node
{
    private array $codeQuotes;

    public function __construct(
        public string $content,
    ) {
        $this->codeQuotes = $this->extractCodeQuotes($content);
    }

    public function codeQuotes(): array
    {
        return $this->codeQuotes;
    }

    private function extractCodeQuotes(string $content): array
    {
        $matches = [];
        preg_match_all('/(?<!`)`([^`]+)`(?!`)/', $content, $matches);
        return $matches[1] ?? [];
    }
}