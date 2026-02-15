<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class ReadFileToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: 'read_file',
            description: <<<'DESC'
Read the contents of a file with line numbers. Use to examine file content after finding it with search_files.

Examples:
- "composer.json" → read composer.json from project root
- "src/Config.php" → read specific file by path
- {"path": "large.log", "offset": 100, "limit": 50} → read lines 101-150

Returns numbered lines. For large files, use offset/limit to read specific sections.
DESC,
            metadata: [
                'name' => 'read_file',
                'summary' => 'Read text files with line numbers and pagination.',
                'namespace' => 'file',
                'tags' => ['file', 'read', 'lines'],
            ],
            instructions: [
                'parameters' => [
                    'path' => 'File path, relative to allowed base directory or absolute if policy allows.',
                    'offset' => '0-based starting line offset.',
                    'limit' => 'Maximum number of lines to return.',
                ],
                'returns' => 'Numbered lines as text, or an explicit error message.',
                'usage' => [
                    'Use search_files or list_dir first when path is unknown.',
                    'Use offset/limit to paginate large files.',
                ],
                'errors' => [
                    'Invalid path, directory path, binary file, or out-of-range offset.',
                    'Sandbox command failures surface as error text.',
                ],
            ],
        );
    }
}
