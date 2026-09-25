<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrieval;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class RetrievalSearchToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: RetrievalSearchTool::TOOL_NAME,
            description: 'Search authorized knowledge and return bounded, citation-labelled evidence.',
            metadata: [
                'name' => RetrievalSearchTool::TOOL_NAME,
                'summary' => 'Search authorized retrieval evidence.',
                'namespace' => 'retrieval',
                'tags' => ['retrieval', 'search', 'rag'],
            ],
            instructions: [
                'parameters' => [
                    'query' => 'Natural-language search query.',
                    'limit' => 'Requested result count; the trusted policy enforces the maximum.',
                ],
                'returns' => 'Bounded untrusted evidence plus citation IDs; never vectors.',
            ],
        );
    }
}
