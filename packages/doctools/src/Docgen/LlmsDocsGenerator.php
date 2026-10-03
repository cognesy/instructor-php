<?php declare(strict_types=1);

namespace Cognesy\Doctools\Docgen;

use Cognesy\Doctools\Docgen\Data\GenerationResult;

/**
 * Generates LLM-friendly documentation files.
 *
 * Produces two output files:
 * - llms.txt: Index file with links and descriptions following the llms.txt standard
 * - llms-full.txt: Complete documentation concatenated into a single file
 */
class LlmsDocsGenerator
{
    private const FILE_SEPARATOR = "\n================================================================================\n";
    private const APPROX_TOKENS_PER_CHAR = 0.25; // rough estimate for English text

    public function __construct(
        private string $projectName = 'Instructor for PHP',
        private string $projectDescription = 'Structured data extraction in PHP, powered by LLMs. Define a PHP class, get a validated object back.',
        private string $linkPrefix = '/llms',
        private array $optionalSections = [],
    ) {}

    /**
     * Generate llms.txt index file from MkDocs navigation structure.
     *
     * @param array $navigation MkDocs navigation array from NavigationBuilder
     * @param string $outputPath Path to write llms.txt
     * @param string|null $sourceDir Markdown source dir; frontmatter descriptions become link notes
     * @return GenerationResult
     */
    public function generateIndex(array $navigation, string $outputPath, ?string $sourceDir = null): GenerationResult
    {
        $startTime = microtime(true);

        try {
            $content = $this->renderIndex($navigation, $sourceDir);

            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $result = file_put_contents($outputPath, $content);

            if ($result === false) {
                return GenerationResult::failure(
                    errors: ['Failed to write llms.txt'],
                    duration: microtime(true) - $startTime,
                    message: 'Failed to generate llms.txt',
                );
            }

            return GenerationResult::success(
                filesCreated: 1,
                duration: microtime(true) - $startTime,
                message: sprintf('Generated llms.txt (%s)', $this->formatSize($result)),
            );

        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                duration: microtime(true) - $startTime,
                message: 'Failed to generate llms.txt',
            );
        }
    }

    /**
     * Generate llms-full.txt by concatenating all markdown files.
     *
     * @param array $navigation MkDocs navigation array (defines file order)
     * @param string $sourceDir Base directory containing markdown files
     * @param string $outputPath Path to write llms-full.txt
     * @param array $excludePatterns Patterns to exclude (e.g., ['release-notes/'])
     * @return GenerationResult
     */
    public function generateFull(
        array $navigation,
        string $sourceDir,
        string $outputPath,
        array $excludePatterns = ['release-notes/'],
    ): GenerationResult {
        $startTime = microtime(true);
        $filesProcessed = 0;

        try {
            // Extract file paths from navigation in order
            $filePaths = $this->extractFilePaths($navigation);

            // Filter out excluded patterns
            $filePaths = $this->filterExcluded($filePaths, $excludePatterns);

            $content = $this->renderFullHeader();

            foreach ($filePaths as $relativePath) {
                $fullPath = rtrim($sourceDir, '/') . '/' . $relativePath;

                if (!file_exists($fullPath)) {
                    continue;
                }

                $fileContent = file_get_contents($fullPath);
                if ($fileContent === false) {
                    continue;
                }

                // Strip YAML frontmatter
                $fileContent = $this->stripFrontmatter($fileContent);

                // Add file section
                $content .= self::FILE_SEPARATOR;
                $content .= "FILE: {$relativePath}\n";
                $content .= "SOURCE: " . $this->prefixPath($relativePath) . "\n";
                $content .= self::FILE_SEPARATOR;
                $content .= "\n" . trim($fileContent) . "\n";

                $filesProcessed++;
            }

            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $result = file_put_contents($outputPath, $content);

            if ($result === false) {
                return GenerationResult::failure(
                    errors: ['Failed to write llms-full.txt'],
                    filesProcessed: $filesProcessed,
                    duration: microtime(true) - $startTime,
                    message: 'Failed to generate llms-full.txt',
                );
            }

            $tokenEstimate = $this->estimateTokens($result);

            return GenerationResult::success(
                filesProcessed: $filesProcessed,
                filesCreated: 1,
                duration: microtime(true) - $startTime,
                message: sprintf(
                    'Generated llms-full.txt (%s, ~%dk tokens)',
                    $this->formatSize($result),
                    round($tokenEstimate / 1000)
                ),
            );

        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                filesProcessed: $filesProcessed,
                duration: microtime(true) - $startTime,
                message: 'Failed to generate llms-full.txt',
            );
        }
    }

    /**
     * Copy the MkDocs markdown and asset tree into the LLM content namespace.
     */
    public function mirrorSourceTree(
        string $sourceDir,
        string $outputDir,
        array $excludeBasenames = [],
    ): GenerationResult {
        $startTime = microtime(true);
        $filesCreated = 0;
        $excludeBasenames = array_values(array_unique([...$excludeBasenames, '.DS_Store']));

        try {
            if (is_dir($outputDir)) {
                $this->deleteDirectory($outputDir);
            }

            mkdir($outputDir, 0755, true);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $relativePath = substr($item->getPathname(), strlen(rtrim($sourceDir, '/')) + 1);

                if (in_array(basename($relativePath), $excludeBasenames, true)) {
                    continue;
                }

                $targetPath = rtrim($outputDir, '/') . '/' . $relativePath;

                if ($item->isDir()) {
                    if (!is_dir($targetPath)) {
                        mkdir($targetPath, 0755, true);
                    }
                    continue;
                }

                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                copy($item->getPathname(), $targetPath);
                $filesCreated++;
            }

            return GenerationResult::success(
                filesCreated: $filesCreated,
                duration: microtime(true) - $startTime,
                message: sprintf('Mirrored LLM content tree (%d files)', $filesCreated),
            );
        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                filesProcessed: $filesCreated,
                duration: microtime(true) - $startTime,
                message: 'Failed to mirror LLM content tree',
            );
        }
    }

    /**
     * Render the llms.txt index content.
     *
     * Each top-level navigation section becomes an H2 file list. Groups listed in
     * $optionalSections are moved to a trailing "## Optional" section, which the
     * llms.txt standard reserves for links an agent can skip.
     */
    private function renderIndex(array $navigation, ?string $sourceDir): string
    {
        $output = "# {$this->projectName}\n\n";
        $output .= "> {$this->projectDescription}\n\n";
        $optional = [];

        foreach ($navigation as $section) {
            foreach ($section as $sectionTitle => $items) {
                if ($this->isOptional($sectionTitle)) {
                    $optional[] = [$sectionTitle => $items];
                    continue;
                }
                $kept = array_values(array_filter($items, fn(array $item): bool => !$this->isOptionalGroup($item)));
                $optional = [...$optional, ...array_values(array_filter($items, fn(array $item): bool => $this->isOptionalGroup($item)))];
                $output .= "## {$sectionTitle}\n\n";
                $output .= ltrim($this->renderNavItems($kept, [], $sourceDir), "\n");
                $output .= "\n";
            }
        }

        if ($optional !== []) {
            $output .= "## Optional\n\n";
            $output .= ltrim($this->renderNavItems($optional, [], $sourceDir), "\n");
            $output .= "\n";
        }

        return $output;
    }

    /**
     * Render navigation items as markdown links. Nested groups become H3 headings
     * carrying their full path (e.g. "Instructor / Concepts"), so every list stays
     * flat and attributable to its parent.
     *
     * @param string[] $trail
     */
    private function renderNavItems(array $items, array $trail, ?string $sourceDir): string
    {
        $links = '';
        $groups = '';

        foreach ($items as $item) {
            foreach ($item as $title => $value) {
                $links .= match (true) {
                    is_string($value) => $this->renderLink((string) $title, $value, $sourceDir),
                    default => '',
                };
                $groups .= match (true) {
                    is_array($value) => $this->renderGroup([...$trail, (string) $title], $value, $sourceDir),
                    default => '',
                };
            }
        }

        return $links . $groups;
    }

    /** @param string[] $trail */
    private function renderGroup(array $trail, array $items, ?string $sourceDir): string
    {
        return "\n### " . implode(' / ', $trail) . "\n\n" . $this->renderNavItems($items, $trail, $sourceDir);
    }

    private function renderLink(string $title, string $path, ?string $sourceDir): string
    {
        $description = $this->description($path, $sourceDir);

        return match ($description) {
            '' => "- [{$title}](" . $this->prefixPath($path) . ")\n",
            default => "- [{$title}](" . $this->prefixPath($path) . "): {$description}\n",
        };
    }

    private function description(string $path, ?string $sourceDir): string
    {
        $fullPath = rtrim($sourceDir ?? '', '/') . '/' . ltrim($path, '/');
        $content = match (true) {
            $sourceDir === null, !is_file($fullPath) => '',
            default => (string) file_get_contents($fullPath),
        };
        $matched = preg_match('/^(?:\xEF\xBB\xBF)?---\r?\n(.*?)\r?\n---\r?\n/s', $content, $frontmatter) === 1
            && preg_match('/^description:\s*(.+)$/m', $frontmatter[1], $line) === 1;

        return match ($matched) {
            true => $this->unquote(trim($line[1])),
            false => '',
        };
    }

    private function unquote(string $value): string
    {
        $quote = $value[0] ?? '';
        $unquoted = match (true) {
            strlen($value) < 2, !in_array($quote, ["'", '"'], true), !str_ends_with($value, $quote) => $value,
            $quote === "'" => str_replace("''", "'", substr($value, 1, -1)),
            default => stripcslashes(substr($value, 1, -1)),
        };

        return trim((string) preg_replace('/\s+/', ' ', $unquoted));
    }

    private function isOptional(string $title): bool
    {
        return in_array($title, $this->optionalSections, true);
    }

    private function isOptionalGroup(array $item): bool
    {
        $title = (string) array_key_first($item);

        return is_array($item[$title] ?? null) && $this->isOptional($title);
    }

    private function prefixPath(string $path): string
    {
        $prefix = trim($this->linkPrefix);
        if ($prefix === '') {
            return $path;
        }

        return rtrim($prefix, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Render the header for llms-full.txt.
     */
    private function renderFullHeader(): string
    {
        return <<<HEADER
# {$this->projectName}

> {$this->projectDescription}

This file contains the complete documentation for {$this->projectName}.
It is optimized for LLM consumption and includes all documentation pages
concatenated into a single file.

HEADER;
    }

    /**
     * Extract file paths from navigation structure in order.
     *
     * @return string[]
     */
    private function extractFilePaths(array $navigation): array
    {
        $paths = [];

        foreach ($navigation as $section) {
            foreach ($section as $items) {
                $paths = array_merge($paths, $this->extractPathsFromItems($items));
            }
        }

        return $paths;
    }

    /**
     * Recursively extract file paths from navigation items.
     *
     * @return string[]
     */
    private function extractPathsFromItems(array $items): array
    {
        $paths = [];

        foreach ($items as $item) {
            foreach ($item as $value) {
                if (is_string($value)) {
                    $paths[] = $value;
                } elseif (is_array($value)) {
                    $paths = array_merge($paths, $this->extractPathsFromItems($value));
                }
            }
        }

        return $paths;
    }

    /**
     * Filter out paths matching exclusion patterns.
     *
     * @param string[] $paths
     * @param string[] $excludePatterns
     * @return string[]
     */
    private function filterExcluded(array $paths, array $excludePatterns): array
    {
        if (empty($excludePatterns)) {
            return $paths;
        }

        return array_filter($paths, function (string $path) use ($excludePatterns): bool {
            foreach ($excludePatterns as $pattern) {
                if (str_contains($path, $pattern)) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Strip YAML frontmatter from markdown content.
     */
    private function stripFrontmatter(string $content): string
    {
        // Match YAML frontmatter at the start of the file, allowing BOM and CRLF line endings.
        if (preg_match('/^(?:\xEF\xBB\xBF)?---\r?\n.*?\r?\n---\r?\n/s', $content, $matches)) {
            return substr($content, strlen($matches[0]));
        }

        return $content;
    }

    /**
     * Estimate token count from byte size.
     */
    private function estimateTokens(int $bytes): int
    {
        return (int) ($bytes * self::APPROX_TOKENS_PER_CHAR);
    }

    /**
     * Format file size in human-readable format.
     */
    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        } else {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        }
    }

    private function deleteDirectory(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
