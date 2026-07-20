<?php

declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class WriteFileToolDescriptor extends ToolDescriptor
{
    public function __construct(string $name = 'write_file') {
        parent::__construct(
            name: $name,
            description: <<<'DESC'
Create a new file with the provided content. The parent directory must already exist.
Refuses to overwrite different content; retrying identical content succeeds as a no-op.

Examples:
- path="config.json", content='{"debug": true}'
- path="src/NewClass.php", content="<?php\n\nclass NewClass {}\n"

Use the edit tool to change an existing file. The new file is streamed and committed atomically.
DESC,
            metadata: [
                'name' => $name,
                'summary' => 'Atomically create a new file without implicit directories or overwrites.',
                'namespace' => 'file',
                'tags' => ['file', 'write', 'create'],
            ],
            instructions: [
                'parameters' => [
                    'path' => 'New file path under an existing parent directory.',
                    'content' => 'Full content to write.',
                ],
                'returns' => 'Written byte and line count, identical-content no-op, or explicit no-change error.',
            ],
        );
    }
}
