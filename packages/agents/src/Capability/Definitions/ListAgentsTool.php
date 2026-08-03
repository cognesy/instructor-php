<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Definitions;

use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use Override;

final class ListAgentsTool extends SimpleTool
{
    public const TOOL_NAME = 'list_agents';

    public function __construct(private readonly CanManageAgentDefinitions $registry) {
        parent::__construct(new ListAgentsToolDescriptor());
    }

    /** @return array{success: true, count: int, agents: list<array{name: string, description: string}>} */
    #[Override]
    public function __invoke(mixed ...$args): array {
        $agents = [];
        foreach ($this->registry->all() as $definition) {
            $agents[] = [
                'name' => $definition->name,
                'description' => $definition->description,
            ];
        }
        return ['success' => true, 'count' => count($agents), 'agents' => $agents];
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters'),
        )->toArray());
    }
}
