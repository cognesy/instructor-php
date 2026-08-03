<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Definitions;

use Cognesy\Agents\Template\AgentDefinitionSerializer;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use InvalidArgumentException;
use Override;

final class ReadAgentTool extends SimpleTool
{
    public const TOOL_NAME = 'read_agent';

    public function __construct(
        private readonly CanManageAgentDefinitions $registry,
        private readonly AgentDefinitionSerializer $serializer,
    ) {
        parent::__construct(new ReadAgentToolDescriptor());
    }

    /** @return array{success: true, name: string, source: string} */
    #[Override]
    public function __invoke(mixed ...$args): array {
        $name = $this->arg($args, 'name', 0);
        if (!is_string($name) || $name === '') {
            throw new InvalidArgumentException("'name' must be a non-empty string.");
        }
        $definition = $this->registry->get($name);
        return [
            'success' => true,
            'name' => $definition->name,
            'source' => $this->serializer->toMarkdown($definition),
        ];
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('name', 'Registered agent name.'),
                ])
                ->withRequiredProperties(['name']),
        )->toArray());
    }
}
