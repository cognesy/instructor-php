<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen;

use Cognesy\Doctor\Docgen\Data\GenerationResult;

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
    ) {}

    /**
     * Generate llms.txt index file from MkDocs navigation structure.
     *
     * @param array $navigation MkDocs navigation array from NavigationBuilder
     * @param string $outputPath Path to write llms.txt
     * @return GenerationResult
     */
    public function generateIndex(array $navigation, string $outputPath): GenerationResult
    {
        $startTime = microtime(true);

        try {
            $content = $this->renderIndex($navigation);

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
     * Render the llms.txt index content.
     */
    private function renderIndex(array $navigation): string
    {
        $output = "# {$this->projectName}\n\n";
        $output .= "> {$this->projectDescription}\n\n";

        foreach ($navigation as $section) {
            foreach ($section as $sectionTitle => $items) {
                $output .= "## {$sectionTitle}\n\n";
                $output .= $this->renderNavItems($items, 0);
                $output .= "\n";
            }
        }

        return $output;
    }

    /**
     * Recursively render navigation items as markdown links.
     */
    private function renderNavItems(array $items, int $depth): string
    {
        $output = '';
        $indent = str_repeat('  ', $depth);

        foreach ($items as $item) {
            foreach ($item as $title => $value) {
                if (is_string($value)) {
                    // It's a file path - render as link
                    $output .= "{$indent}- [{$title}]({$value})\n";
                } elseif (is_array($value)) {
                    // It's a nested group
                    if ($depth === 0) {
                        // First level nesting - use ### header
                        $output .= "\n### {$title}\n\n";
                        $output .= $this->renderNavItems($value, 0);
                    } else {
                        // Deeper nesting - use bold label
                        $output .= "{$indent}- **{$title}**\n";
                        $output .= $this->renderNavItems($value, $depth + 1);
                    }
                }
            }
        }

        return $output;
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
        // Match YAML frontmatter at the start of the file
        if (preg_match('/^---\s*\n.*?\n---\s*\n/s', $content, $matches)) {
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
}
