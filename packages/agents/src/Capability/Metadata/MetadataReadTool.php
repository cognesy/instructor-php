<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Metadata;

use Cognesy\Agents\Tool\Tools\StateAwareTool;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

/**
 * Tool for reading data from agent metadata.
 *
 * Allows the agent to inspect previously stored data:
 *   read_metadata(key: "current_lead")
 */
class MetadataReadTool extends StateAwareTool
{
    public const TOOL_NAME = 'read_metadata';

    public function __construct() {
        parent::__construct(new MetadataReadToolDescriptor());
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $key = $this->arg($args, 'key', 0, '');

        if ($key === '') {
            return 'Error: Key cannot be empty';
        }

        if ($this->agentState === null) {
            return 'Error: Agent state not available';
        }

        $metadata = $this->agentState->metadata();

        if (!$metadata->hasKey($key)) {
            $available = $metadata->keys();
            $hint = $available !== []
                ? ' Available keys: ' . implode(', ', $available)
                : ' No metadata stored yet.';
            return "Error: Key '{$key}' not found.{$hint}";
        }

        $value = $metadata->get($key);

        return match (true) {
            is_string($value) => $value,
            is_array($value), is_object($value) => Json::encode($value),
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            default => (string) $value,
        };
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('key', 'The key to read from metadata'),
                ])
                ->withRequiredProperties(['key'])
        )->toArray();
    }
}
