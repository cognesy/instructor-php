<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Metadata;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class MetadataWriteToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: MetadataWriteTool::TOOL_NAME,
            description: <<<'DESC'
Store a value in agent metadata for use by other tools.

Use this to pass data between tool calls. For example:
1. Extract data and store: store_metadata(key: "current_lead", value: {"name": "John", "email": "john@example.com"})
2. Later tool reads it: save_lead(metadata_key: "current_lead")

Keys should be descriptive: "current_lead", "extracted_contacts", "scraped_content", etc.
DESC,
            metadata: [
                'name' => MetadataWriteTool::TOOL_NAME,
                'summary' => 'Store a metadata value for later steps/tools.',
                'namespace' => 'metadata',
                'tags' => ['metadata', 'write', 'store'],
            ],
            instructions: [
                'parameters' => [
                    'key' => 'Metadata key.',
                    'value' => 'Any JSON-serializable value.',
                ],
                'returns' => 'Write result with success flag and error if rejected.',
            ],
        );
    }
}
