<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class WriteFileToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: 'write_file',
            description: <<<'DESC'
Write content to a file. Creates file and parent directories if needed. Overwrites existing files.

Examples:
- path="config.json", content='{"debug": true}'
- path="src/NewClass.php", content="<?php\n\nclass NewClass {}\n"

Use edit_file for partial changes. write_file replaces entire file content.
DESC,
            metadata: [
                'name' => 'write_file',
                'summary' => 'Write full file contents, creating parent directories if needed.',
                'namespace' => 'file',
                'tags' => ['file', 'write', 'create'],
            ],
            instructions: [
                'parameters' => [
                    'path' => 'Target file path.',
                    'content' => 'Full content to write.',
                ],
                'returns' => 'Written byte and line count, or explicit error.',
            ],
        );
    }
}
