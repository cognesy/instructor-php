<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SearchFilesTool extends SimpleTool
{
    private string $baseDir;
    private int $maxResults;

    public function __construct(
        string $baseDir,
        int $maxResults = 10,
    ) {
        parent::__construct(new SearchFilesToolDescriptor());
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

            if ($relativePaths !== []) {
                $patternResults[$pattern] = $relativePaths;
                $allFiles = array_merge($allFiles, $relativePaths);
            }
        }

        // Deduplicate and limit
        $allFiles = array_unique($allFiles);
        $allFiles = array_slice($allFiles, 0, $this->maxResults);

        if ($allFiles === []) {
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
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (fnmatch($filePattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('pattern', 'Search pattern. Examples: "composer.json" (recursive), "**/*.php" (recursive glob), "*.json" (root only), "./README.md" (exact path)'),
                    JsonSchema::array('patterns', JsonSchema::string(), 'Multiple patterns to search at once. Example: ["composer.json", "package.json", "*.xml"]'),
                ])
        )->toArray();
    }
}
