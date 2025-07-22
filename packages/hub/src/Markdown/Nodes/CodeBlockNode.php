<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown\Nodes;

final readonly class CodeBlockNode extends Node
{
    public readonly int $linesOfCode;

    public function __construct(
        public string $id,
        public string $language,
        public string $content,  // This is now the clean content without PHP tags
        public array $metadata = [],
        public bool $hasPhpOpenTag = false,
        public bool $hasPhpCloseTag = false,
        public string $originalContent = '',  // Original content with PHP tags if needed
    ) {
        $this->linesOfCode = $this->calculateLinesOfCode();
    }

    public function getContentWithoutPhpTags(): string {
        return $this->content;  // Content is already clean
    }

    public function hasPhpTags(): bool {
        return $this->hasPhpOpenTag || $this->hasPhpCloseTag;
    }

    private function calculateLinesOfCode(): int {
        $lines = explode("\n", $this->content);
        $count = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip empty lines
            if ($trimmed === '') {
                continue;
            }

            // Skip comment lines based on language
            if ($this->isCommentLine($trimmed)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function isCommentLine(string $line): bool {
        $language = strtolower($this->language);

        // Handle different comment styles based on language
        return match (true) {
            // C-style languages (PHP, JS, Java, C#, etc.)
            in_array($language, ['php', 'javascript', 'js', 'java', 'c', 'cpp', 'csharp', 'c#', 'typescript', 'ts']) =>
                str_starts_with($line, '//') || str_starts_with($line, '/*') || str_starts_with($line, '*'),

            // Python, Ruby, Bash, etc.
            in_array($language, ['python', 'py', 'ruby', 'rb', 'bash', 'shell', 'sh']) =>
                str_starts_with($line, '#') && !str_starts_with($line, '#!'),

            // HTML/XML
            in_array($language, ['html', 'xml']) =>
            str_starts_with($line, '<!--'),

            // CSS
            $language === 'css' =>
                str_starts_with($line, '/*') || str_starts_with($line, '*'),

            // SQL
            $language === 'sql' =>
                str_starts_with($line, '--') || str_starts_with($line, '/*') || str_starts_with($line, '*'),

            // Default: no comment detection for unknown languages
            default => false,
        };
    }
}
