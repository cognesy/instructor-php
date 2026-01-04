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
            description: 'Search for files matching a glob pattern in the project',
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

        // Handle recursive patterns with **
        if (str_contains($pattern, '**')) {
            $files = $this->recursiveGlob($pattern);
        } else {
            $fullPattern = $this->baseDir . '/' . $pattern;
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

    private function recursiveGlob(string $pattern): array {
        $filePattern = basename($pattern);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->baseDir, FilesystemIterator::SKIP_DOTS),
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
                            'description' => 'Glob pattern to match files (e.g., "src/*.php", "**/*.md")',
                        ],
                    ],
                    'required' => ['pattern'],
                ],
            ],
        ];
    }
}
