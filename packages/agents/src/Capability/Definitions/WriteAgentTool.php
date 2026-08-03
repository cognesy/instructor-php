<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Definitions;

use Cognesy\Agents\Template\AgentDefinitionValidator;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\FileAgentDefinitionStore;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use InvalidArgumentException;
use Override;
use Throwable;

final class WriteAgentTool extends SimpleTool
{
    public const TOOL_NAME = 'write_agent';

    public function __construct(
        private readonly CanManageAgentDefinitions $registry,
        private readonly AgentDefinitionValidator $validator,
        private readonly FileAgentDefinitionStore $store,
    ) {
        parent::__construct(new WriteAgentToolDescriptor());
    }

    /** @return array<string, mixed> */
    #[Override]
    public function __invoke(mixed ...$args): array {
        $data = $this->arg($args, 'definition', 0);
        $overwrite = $this->arg($args, 'overwrite', 1, false);
        if (!is_array($data)) {
            throw new InvalidArgumentException("'definition' must be an object.");
        }
        if (!is_bool($overwrite)) {
            throw new InvalidArgumentException("'overwrite' must be a boolean.");
        }

        $definition = AgentDefinition::fromArray($data);
        $report = $this->validator->validate($definition);
        if (!$report->isValid()) {
            return ['success' => false, 'problems' => $report->toArray()];
        }

        try {
            $stored = $this->store->save($definition, $overwrite);
            $this->registry->loadFromFile($stored->path);
        } catch (Throwable $throwable) {
            return ['success' => false, 'error' => $throwable->getMessage()];
        }
        return [
            'success' => true,
            'name' => $definition->name,
            'path' => $stored->path,
            'created' => !$stored->replaced,
            'replaced' => $stored->replaced,
        ];
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::object(
                        name: 'definition',
                        description: 'Complete AgentDefinition object. The name selects <root>/<name>.md.',
                        additionalProperties: true,
                    ),
                    JsonSchema::boolean('overwrite', 'Replace an existing definition. Defaults to false.'),
                ])
                ->withRequiredProperties(['definition']),
        )->toArray());
    }
}
