<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Metadata;

use Cognesy\Agents\Core\Tools\BaseTool;
use Cognesy\Utils\Json\Json;

/**
 * Tool for storing data in agent metadata.
 *
 * The agent can use this to pass data between tool calls:
 *   store_metadata(key: "current_lead", value: {...})
 *
 * Other tools can then read this data using metadata_key parameter.
 */
class MetadataWriteTool extends BaseTool
{
    public const TOOL_NAME = 'store_metadata';

    public function __construct(
        private MetadataPolicy $policy = new MetadataPolicy(),
    ) {
        parent::__construct(
            name: self::TOOL_NAME,
            description: <<<'DESC'
Store a value in agent metadata for use by other tools.

Use this to pass data between tool calls. For example:
1. Extract data and store: store_metadata(key: "current_lead", value: {"name": "John", "email": "john@example.com"})
2. Later tool reads it: save_lead(metadata_key: "current_lead")

Keys should be descriptive: "current_lead", "extracted_contacts", "scraped_content", etc.
DESC,
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): MetadataWriteResult {
        $key = (string) ($args['key'] ?? $args[0] ?? '');
        $value = $args['value'] ?? $args[1] ?? null;

        if ($key === '') {
            return new MetadataWriteResult(
                success: false,
                key: $key,
                value: null,
                error: 'Key cannot be empty',
            );
        }

        if ($this->policy->isReservedKey($key)) {
            return new MetadataWriteResult(
                success: false,
                key: $key,
                value: null,
                error: "Key '{$key}' is reserved for internal use",
            );
        }

        $serialized = Json::encode($value);
        if (strlen($serialized) > $this->policy->maxValueSizeBytes) {
            return new MetadataWriteResult(
                success: false,
                key: $key,
                value: null,
                error: "Value exceeds maximum size of {$this->policy->maxValueSizeBytes} bytes",
            );
        }

        return new MetadataWriteResult(
            success: true,
            key: $key,
            value: $value,
        );
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
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'Unique identifier for this data (e.g., "current_lead", "scraped_content")',
                        ],
                        'value' => [
                            'description' => 'The data to store. Can be any JSON-serializable value: string, number, object, array.',
                        ],
                    ],
                    'required' => ['key', 'value'],
                ],
            ],
        ];
    }
}
