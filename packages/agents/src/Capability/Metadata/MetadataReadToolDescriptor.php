<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Metadata;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class MetadataReadToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: MetadataReadTool::TOOL_NAME,
            description: <<<'DESC'
Read a value from agent metadata that was previously stored.

Use this to inspect data stored by previous tool calls.
Returns the value as JSON, or an error if the key doesn't exist.
DESC,
            metadata: [
                'name' => MetadataReadTool::TOOL_NAME,
                'summary' => 'Read a stored metadata value by key.',
                'namespace' => 'metadata',
                'tags' => ['metadata', 'read'],
            ],
            instructions: [
                'parameters' => [
                    'key' => 'Metadata key to read.',
                ],
                'returns' => 'Stored value rendered as text or JSON.',
            ],
        );
    }
}
