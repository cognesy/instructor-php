<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class ListDirToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: 'list_dir',
            description: <<<'DESC'
List directory contents. Shows files and subdirectories with [file] or [dir] markers.

Examples:
- "." or "" → list project root
- "src" → list src directory
- "packages/addons/src" → list nested directory

Use to explore project structure before searching for specific files.
DESC,
            metadata: [
                'name' => 'list_dir',
                'summary' => 'List directory entries with file/dir markers.',
                'namespace' => 'file',
                'tags' => ['file', 'directory', 'listing'],
            ],
            instructions: [
                'parameters' => [
                    'path' => 'Directory path relative to configured base directory.',
                ],
                'returns' => 'Directory listing as text or an explicit error.',
            ],
        );
    }
}
