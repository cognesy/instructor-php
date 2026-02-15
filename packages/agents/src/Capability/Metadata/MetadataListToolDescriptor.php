<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Metadata;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class MetadataListToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: MetadataListTool::TOOL_NAME,
            description: <<<'DESC'
List all keys currently stored in agent metadata.

Returns a list of available keys with their value types.
Use this to see what data has been stored by previous tool calls.
DESC,
            metadata: [
                'name' => MetadataListTool::TOOL_NAME,
                'summary' => 'List all metadata keys and value types.',
                'namespace' => 'metadata',
                'tags' => ['metadata', 'list', 'inspect'],
            ],
            instructions: [
                'parameters' => [],
                'returns' => 'JSON payload with metadata key list and types.',
            ],
        );
    }
}
