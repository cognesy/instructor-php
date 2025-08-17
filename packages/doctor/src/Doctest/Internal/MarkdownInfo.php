<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Internal;

use Cognesy\Doctor\Markdown\MarkdownFile;

/**
 * Represents markdown file metadata and configuration
 * Encapsulates all markdown-related metadata processing
 */
final readonly class MarkdownInfo
{
    public function __construct(
        public string $path,
        public string $title,
        public string $description,
        public string $caseDir,
        public string $casePrefix,
        public int $minLines,
        public array $includedTypes,
    ) {}

    public static function from(MarkdownFile $markdown): self {
        return new self(
            path: $markdown->path(),
            title: $markdown->metadata('title', ''),
            description: $markdown->metadata('description', ''),
            caseDir: $markdown->metadata('doctest_case_dir', '') ?: 'examples',
            casePrefix: $markdown->metadata('doctest_case_prefix', '') ?: self::generateCasePrefix($markdown->path()),
            minLines: $markdown->metadata('doctest_min_lines', 0),
            includedTypes: $markdown->metadata('doctest_included_types', []),
        );
    }

    private static function generateCasePrefix(string $markdownPath): string {
        $filename = pathinfo($markdownPath, PATHINFO_FILENAME);
        $cleanName = preg_replace('/^([0-9]+[-_\s]*)+/', '', $filename);
        return \Cognesy\Utils\Str::camel($cleanName) . '_';
    }
}