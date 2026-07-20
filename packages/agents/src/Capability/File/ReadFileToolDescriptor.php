<?php

declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class ReadFileToolDescriptor extends ToolDescriptor
{
    public function __construct(string $name = 'read_file') {
        parent::__construct(
            name: $name,
            description: <<<'DESC'
Read the contents of a file with line numbers. Use to examine file content after finding it with search_files.

Examples:
- "composer.json" → read composer.json from project root
- "src/Config.php" → read specific file by path
- {"path": "large.log", "offset": 100, "limit": 50} → read lines 101-150

Returns numbered lines. By default, output is limited to 200 lines or 32 KiB, whichever comes first.
Set larger limit and max_bytes values when a larger window or the complete file is justified.
When more content exists, the result includes the exact offset or bash command needed to continue.
DESC,
            metadata: [
                'name' => $name,
                'summary' => 'Read text files with line numbers and pagination.',
                'namespace' => 'file',
                'tags' => ['file', 'read', 'lines'],
            ],
            instructions: [
                'parameters' => [
                    'path' => 'File path, relative to allowed base directory or absolute if policy allows.',
                    'offset' => '0-based starting line offset.',
                    'limit' => 'Maximum number of lines to return; defaults to 200 with no fixed upper limit.',
                    'max_bytes' => 'Maximum returned bytes; defaults to 32 KiB and can be increased explicitly.',
                ],
                'returns' => 'Numbered lines as text, or an explicit error message.',
                'usage' => [
                    'Use search or directory-listing tools first when the path is unknown.',
                    'Use offset/limit to paginate large files.',
                    'Follow the returned continuation hint when output is truncated.',
                ],
                'errors' => [
                    'Invalid path, directory path, binary file, or out-of-range offset.',
                    'Sandbox command failures surface as error text.',
                ],
            ],
        );
    }
}
