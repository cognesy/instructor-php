<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class SearchFilesToolDescriptor extends ToolDescriptor
{
    public function __construct() {
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
            metadata: [
                'name' => 'search_files',
                'summary' => 'Find files by glob-like filename and path patterns.',
                'namespace' => 'file',
                'tags' => ['file', 'search', 'glob'],
            ],
            instructions: [
                'parameters' => [
                    'pattern' => 'Single filename/path search pattern.',
                    'patterns' => 'Optional list of patterns.',
                ],
                'returns' => 'Matched relative file paths grouped by pattern.',
            ],
        );
    }
}
