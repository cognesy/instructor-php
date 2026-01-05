<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools\File;

use Cognesy\Addons\Agent\Tools\BaseTool;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SearchFilesTool extends BaseTool
{
    private string $baseDir;
    private int $maxResults;

    public function __construct(
        string $baseDir,
        int $maxResults = 10,
    ) {
        parent::__construct(
            name: 'search_files',
            description: <<<'DESC'
Search for files by name/path pattern (not content). Returns matching file paths.

Examples:
- "composer.json" → finds composer.json recursively in all directories
- "*.php" → finds PHP files in root directory only
- "**/*.php" → finds PHP files recursively in all directories
- "src/**/*.php" → finds PHP files recursively under src/
- "./composer.json" → finds composer.json in root directory only
- "Config" → finds files with "Config" in their name, recursively

Note: Use read_file to examine file contents after finding them.
DESC,
        );
        $this->baseDir = rtrim($baseDir, '/');
        $this->maxResults = $maxResults;
    }

    public static function inDirectory(string $baseDir, int $maxResults = 10): self {
        return new self($baseDir, $maxResults);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $patterns = $this->extractPatterns($args);

        $allFiles = [];
        $patternResults = [];

        foreach ($patterns as $pattern) {
            $normalizedPattern = $this->normalizePattern($pattern);

            if (str_contains($normalizedPattern, '**')) {
                $files = $this->recursiveGlob($normalizedPattern);
            } else {
                $fullPattern = $this->baseDir . '/' . $normalizedPattern;
                $files = glob($fullPattern, GLOB_BRACE) ?: [];
                // Filter out directories - only return files
                $files = array_filter($files, 'is_file');
            }

            // Convert to relative paths
            $relativePaths = array_map(
                fn($f) => str_replace($this->baseDir . '/', '', $f),
                $files
            );

            if (!empty($relativePaths)) {
                $patternResults[$pattern] = $relativePaths;
                $allFiles = array_merge($allFiles, $relativePaths);
            }
        }

        // Deduplicate and limit
        $allFiles = array_unique($allFiles);
        $allFiles = array_slice($allFiles, 0, $this->maxResults);

        if (empty($allFiles)) {
            $patternList = implode(', ', $patterns);
            return "No files found matching patterns: {$patternList}";
        }

        // Format output showing which pattern matched what
        $output = "Found " . count($allFiles) . " files:\n";
        foreach ($patternResults as $pattern => $files) {
            /** @var array<int, string> $limited */
            $limited = array_slice($files, 0, 5);
            $output .= "\n[{$pattern}]\n" . implode("\n", $limited);
            if (count($files) > 5) {
                $output .= "\n... and " . (count($files) - 5) . " more";
            }
        }

        return $output;
    }

    private function extractPatterns(array $args): array {
        // Single pattern as string
        if (isset($args['pattern']) && is_string($args['pattern'])) {
            return [$args['pattern']];
        }

        // Multiple patterns as array
        if (isset($args['patterns']) && is_array($args['patterns'])) {
            return array_filter($args['patterns'], 'is_string');
        }

        // Array of pattern objects: [{"pattern": "*.php"}, {"pattern": "*.md"}]
        if (isset($args[0]) && is_array($args[0])) {
            $patterns = [];
            foreach ($args as $item) {
                if (is_array($item) && isset($item['pattern'])) {
                    $patterns[] = $item['pattern'];
                }
            }
            return $patterns ?: ['*.php'];
        }

        // Single positional argument
        if (isset($args[0]) && is_string($args[0])) {
            return [$args[0]];
        }

        return ['*.php'];
    }

    private function normalizePattern(string $pattern): string {
        // Exact path match: starts with ./ or contains directory separator
        // "./composer.json" -> "composer.json" (exact match at root)
        // "src/Config.php" -> "src/Config.php" (exact path match)
        if (str_starts_with($pattern, './')) {
            return substr($pattern, 2);
        }
        if (str_contains($pattern, '/') && !str_contains($pattern, '*')) {
            return $pattern;
        }

        // Check if pattern already has glob characters
        if (preg_match('/[*?\[\]{}]/', $pattern)) {
            return $pattern;
        }

        // No glob characters - treat as substring/extension search
        // "php" -> "**/*php*" (recursive search for files containing "php")
        // "test.php" -> "**/test.php" (recursive search for exact filename)
        if (str_contains($pattern, '.')) {
            // Looks like a filename - search recursively for exact match
            return '**/' . $pattern;
        }

        // Looks like a keyword - search recursively for files containing it
        return '**/*' . $pattern . '*';
    }

    private function recursiveGlob(string $pattern): array {
        // Parse pattern: "packages/addons/**/*.php" -> dir="packages/addons", file="*.php"
        $parts = explode('**/', $pattern, 2);
        $dirPrefix = rtrim($parts[0], '/');
        $filePattern = $parts[1] ?? '*';

        // Determine search root
        $searchRoot = $dirPrefix !== ''
            ? $this->baseDir . '/' . $dirPrefix
            : $this->baseDir;

        if (!is_dir($searchRoot)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($searchRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $files = [];
        foreach ($iterator as $file) {
            if (fnmatch($filePattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'pattern' => [
                            'type' => 'string',
                            'description' => 'Search pattern. Examples: "composer.json" (recursive), "**/*.php" (recursive glob), "*.json" (root only), "./README.md" (exact path)',
                        ],
                        'patterns' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Multiple patterns to search at once. Example: ["composer.json", "package.json", "*.xml"]',
                        ],
                    ],
                ],
            ],
        ];
    }
}
