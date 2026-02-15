<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Metadata;

use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

/**
 * Tool for storing data in agent metadata.
 *
 * The agent can use this to pass data between tool calls:
 *   store_metadata(key: "current_lead", value: {...})
 *
 * Other tools can then read this data using metadata_key parameter.
 */
final class MetadataWriteTool extends SimpleTool
{
    public const TOOL_NAME = 'store_metadata';

    public function __construct(
        private MetadataPolicy $policy = new MetadataPolicy(),
    ) {
        parent::__construct(new MetadataWriteToolDescriptor());
    }

    #[\Override]
    public function __invoke(mixed ...$args): MetadataWriteResult {
        $key = (string) $this->arg($args, 'key', 0, '');
        $value = $this->arg($args, 'value', 1);

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
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::string('key', 'Unique identifier for this data (e.g., "current_lead", "scraped_content")'),
                    JsonSchema::any('value', 'The data to store. Can be any JSON-serializable value: string, number, object, array.'),
                ])
                ->withRequiredProperties(['key', 'value'])
        )->toArray();
    }
}
