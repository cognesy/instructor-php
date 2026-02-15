<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

class ListDirTool extends SimpleTool
{
    private string $baseDir;
    private int $maxEntries;

    public function __construct(
        string $baseDir,
        int $maxEntries = 50,
    ) {
        parent::__construct(new ListDirToolDescriptor());
        $this->baseDir = rtrim($baseDir, '/');
        $this->maxEntries = $maxEntries;
    }

    public static function inDirectory(string $baseDir, int $maxEntries = 50): self {
        return new self($baseDir, $maxEntries);
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $path = $this->arg($args, 'path', 0, '.');

        // Resolve path relative to baseDir
        if (!str_starts_with($path, '/')) {
            $path = $this->baseDir . '/' . $path;
        }

        $path = rtrim($path, '/');

        if (!is_dir($path)) {
            return "Error: '{$path}' is not a directory";
        }

        $entries = @scandir($path);
        if ($entries === false) {
            return "Error: Cannot read directory '{$path}'";
        }

        // Filter out . and ..
        $entries = array_filter($entries, fn($e) => $e !== '.' && $e !== '..');
        $entries = array_values($entries);

        if ($entries === []) {
            return "Directory '{$path}' is empty";
        }

        // Sort: directories first, then files
        usort($entries, function($a, $b) use ($path) {
            $aIsDir = is_dir($path . '/' . $a);
            $bIsDir = is_dir($path . '/' . $b);
            if ($aIsDir && !$bIsDir) return -1;
            if (!$aIsDir && $bIsDir) return 1;
            return strcasecmp($a, $b);
        });

        // Limit entries
        $total = count($entries);
        $entries = array_slice($entries, 0, $this->maxEntries);

        // Format output
        $lines = [];
        foreach ($entries as $entry) {
            $fullPath = $path . '/' . $entry;
            $type = is_dir($fullPath) ? '[dir]' : '[file]';
            $lines[] = "{$type} {$entry}";
        }

        $output = implode("\n", $lines);

        if ($total > $this->maxEntries) {
            $output .= "\n... and " . ($total - $this->maxEntries) . " more entries";
        }

        return $output;
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('path', 'Directory path. Examples: "." (root), "src", "packages/addons"'),
                ])
                ->withRequiredProperties(['path'])
        )->toArray();
    }
}
