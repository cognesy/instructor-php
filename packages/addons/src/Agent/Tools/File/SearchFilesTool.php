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
            description: 'Search for files in the project. Supports glob patterns (*.php, **/*.md) or simple keywords (php, test) which search recursively.',
        );
        $this->baseDir = rtrim($baseDir, '/');
        $this->maxResults = $maxResults;
    }

    public static function inDirectory(string $baseDir, int $maxResults = 10): self {
        return new self($baseDir, $maxResults);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $pattern = $args['pattern'] ?? $args[0] ?? '*.php';

        // Normalize pattern: if no glob characters, treat as substring search
        $normalizedPattern = $this->normalizePattern($pattern);

        // Handle recursive patterns with **
        if (str_contains($normalizedPattern, '**')) {
            $files = $this->recursiveGlob($normalizedPattern);
        } else {
            $fullPattern = $this->baseDir . '/' . $normalizedPattern;
            $files = glob($fullPattern, GLOB_BRACE) ?: [];
        }

        // Limit results
        $files = array_slice($files, 0, $this->maxResults);

        if (empty($files)) {
            return "No files found matching pattern: {$pattern}";
        }

        // Return relative paths
        $relativePaths = array_map(
            fn($f) => str_replace($this->baseDir . '/', '', $f),
            $files
        );

        return "Found " . count($relativePaths) . " files:\n" . implode("\n", $relativePaths);
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
                            'description' => 'Search pattern: exact path (./composer.json, src/Config.php), glob (*.php, **/*.md), filename (composer.json), or keyword (test)',
                        ],
                    ],
                    'required' => ['pattern'],
                ],
            ],
        ];
    }
}
