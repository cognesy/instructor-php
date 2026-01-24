<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Metadata;

use Cognesy\Agents\Agent\Tools\BaseTool;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Json\EmptyObject;

/**
 * Tool for listing all keys in agent metadata.
 *
 * Allows the agent to see what data has been stored:
 *   list_metadata()
 */
class MetadataListTool extends BaseTool
{
    public const TOOL_NAME = 'list_metadata';

    public function __construct() {
        parent::__construct(
            name: self::TOOL_NAME,
            description: <<<'DESC'
List all keys currently stored in agent metadata.

Returns a list of available keys with their value types.
Use this to see what data has been stored by previous tool calls.
DESC,
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        if ($this->agentState === null) {
            return 'Error: Agent state not available';
        }

        $metadata = $this->agentState->metadata();

        if ($metadata->isEmpty()) {
            return 'No metadata stored. Use store_metadata(key, value) to store data.';
        }

        $entries = [];
        foreach ($metadata as $key => $value) {
            $type = $this->describeType($value);
            $entries[] = [
                'key' => $key,
                'type' => $type,
            ];
        }

        return Json::encode([
            'count' => count($entries),
            'keys' => $entries,
        ]);
    }

    private function describeType(mixed $value): string {
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) => 'array[' . count($value) . ']',
            is_array($value) => 'object',
            is_object($value) => 'object<' . get_class($value) . '>',
            default => gettype($value),
        };
    }

    #[\Override]
    public function toToolSchema(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => [
                    'type' => 'object',
                    'properties' => new EmptyObject(),
                    'required' => [],
                ],
            ],
        ];
    }
}
