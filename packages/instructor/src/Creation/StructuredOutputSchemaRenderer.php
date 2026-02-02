<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\SchemaRendering;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Factories\JsonSchemaToSchema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Schema\Visitors\SchemaToJsonSchema;

final class StructuredOutputSchemaRenderer
{
    private StructuredOutputConfig $config;
    private SchemaFactory $schemaFactory;
    private JsonSchemaToSchema $schemaConverter;

    public function __construct(StructuredOutputConfig $config) {
        $this->config = $config;
        $this->schemaConverter = new JsonSchemaToSchema(
            defaultToolName: $config->toolName(),
            defaultToolDescription: $config->toolDescription(),
        );
        $this->schemaFactory = new SchemaFactory(
            useObjectReferences: $config->useObjectReferences(),
            schemaConverter: $this->schemaConverter,
        );
    }

    public function schemaFactory() : SchemaFactory {
        return $this->schemaFactory;
    }

    public function schemaFromJsonSchema(array $jsonSchema) : Schema {
        return $this->schemaConverter->fromJsonSchema($jsonSchema);
    }

    public function renderFromSchema(Schema $schema) : SchemaRendering {
        $toolCallBuilder = $this->makeToolCallBuilder();
        $jsonSchema = (new SchemaToJsonSchema)->toArray(
            $schema,
            $toolCallBuilder->onObjectRef(...)
        );

        return new SchemaRendering(
            jsonSchema: $jsonSchema,
            toolCallSchema: $this->renderToolCallSchemaFrom(
                toolCallBuilder: $toolCallBuilder,
                jsonSchema: $jsonSchema,
            ),
        );
    }

    public function renderToolCallSchema(array $jsonSchema) : array {
        return $this->renderToolCallSchemaFrom(
            toolCallBuilder: $this->makeToolCallBuilder(),
            jsonSchema: $jsonSchema,
        );
    }

    public function renderResponseFormat(
        array $jsonSchema,
        string $schemaName,
        string $toolDescription,
    ) : array {
        return self::responseFormatFor(
            mode: $this->config->outputMode(),
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            toolDescription: $toolDescription,
        );
    }

    public static function responseFormatFor(
        OutputMode $mode,
        array $jsonSchema,
        string $schemaName,
        string $toolDescription,
    ) : array {
        return match($mode) {
            OutputMode::Json => [
                'type' => 'json_object',
                'schema' => $jsonSchema,
            ],
            OutputMode::JsonSchema => [
                'type' => 'json_schema',
                'description' => $toolDescription,
                'json_schema' => [
                    'name' => $schemaName,
                    'schema' => $jsonSchema,
                    'strict' => true,
                ],
            ],
            default => [],
        };
    }

    private function renderToolCallSchemaFrom(
        ToolCallBuilder $toolCallBuilder,
        array $jsonSchema,
    ) : array {
        return $toolCallBuilder->renderToolCall(
            $jsonSchema,
            $this->config->toolName(),
            $this->config->toolDescription(),
        );
    }

    private function makeToolCallBuilder() : ToolCallBuilder {
        return new ToolCallBuilder($this->schemaFactory);
    }
}
