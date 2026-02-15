<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Tools;

use Cognesy\Agents\Tool\ToolDescriptor;

final readonly class ToolsToolDescriptor extends ToolDescriptor
{
    public function __construct() {
        parent::__construct(
            name: 'tools',
            description: 'List, search, and inspect available tools. Use to discover tools without loading full specs.',
            metadata: [
                'name' => 'tools',
                'summary' => 'Inspect available tools and fetch detailed help for one tool.',
                'namespace' => 'tools',
                'tags' => ['discovery', 'introspection', 'registry'],
            ],
            instructions: [
                'parameters' => [
                    'action' => 'One of: list, help, search.',
                    'tool' => 'Tool name for help action.',
                    'query' => 'Search query for search action.',
                    'limit' => 'Optional max number of returned entries.',
                ],
                'returns' => 'Structured response with success flag and data/error payload.',
            ],
        );
    }
}
