<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrieval;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class RetrievalReadToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: RetrievalReadTool::TOOL_NAME,
            description: 'Read one bounded excerpt through the application authorization boundary.',
            metadata: [
                'name' => RetrievalReadTool::TOOL_NAME,
                'summary' => 'Read authorized retrieval evidence.',
                'namespace' => 'retrieval',
                'tags' => ['retrieval', 'read', 'rag'],
            ],
            instructions: [
                'parameters' => ['reference' => 'Opaque evidence reference returned by an authorized search.'],
                'returns' => 'A bounded evidence excerpt, or an unavailable result.',
            ],
        );
    }
}
