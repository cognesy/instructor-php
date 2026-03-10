<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\SchemaRendering;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\JsonSchemaParser;
use Cognesy\Schema\JsonSchemaRenderer;
use Cognesy\Schema\SchemaFactory;

final class StructuredOutputSchemaRenderer
{
    private StructuredOutputConfig $config;
    private SchemaFactory $schemaFactory;
    private JsonSchemaParser $schemaConverter;

    public function __construct(StructuredOutputConfig $config) {
        $this->config = $config;
        $this->schemaConverter = new JsonSchemaParser(
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
        $jsonSchema = (new JsonSchemaRenderer)->toArray(
            $schema,
            $toolCallBuilder->onObjectRef(...)
        );

        return new SchemaRendering(
            jsonSchema: $jsonSchema,
            toolDefinitions: $this->renderToolCallSchemaFrom(
                toolCallBuilder: $toolCallBuilder,
                jsonSchema: $jsonSchema,
            ),
        );
    }

    public function renderToolCallSchema(array $jsonSchema) : ToolDefinitions {
        return $this->renderToolCallSchemaFrom(
            toolCallBuilder: $this->makeToolCallBuilder(),
            jsonSchema: $jsonSchema,
        );
    }

    public function renderResponseFormat(
        array $jsonSchema,
        string $schemaName,
        string $toolDescription,
    ) : ResponseFormat {
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
    ) : ResponseFormat {
        return match($mode) {
            OutputMode::Json => ResponseFormat::jsonObject(),
            OutputMode::JsonSchema => ResponseFormat::jsonSchema(
                schema: $jsonSchema,
                name: $schemaName,
                strict: true,
            ),
            default => ResponseFormat::empty(),
        };
    }

    private function renderToolCallSchemaFrom(
        ToolCallSchemaBuilder $toolCallBuilder,
        array $jsonSchema,
    ) : ToolDefinitions {
        return $toolCallBuilder->renderToolDefinitions(
            $jsonSchema,
            $this->config->toolName(),
            $this->config->toolDescription(),
        );
    }

    private function makeToolCallBuilder() : ToolCallSchemaBuilder {
        return new ToolCallSchemaBuilder($this->schemaFactory);
    }
}
