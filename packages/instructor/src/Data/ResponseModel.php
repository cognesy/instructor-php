<?php declare(strict_types=1);
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

class ResponseModel implements CanProvideJsonSchema
{
    private mixed $instance;

    private string $class;
    private Schema $schema;
    private array $jsonSchema;
    private array $toolCallSchema;
    private array $responseFormat;
    private string $toolName;
    private string $toolDescription;
    private string $schemaName;
    private string $schemaDescription;
    private bool $useObjectReferences;

    private StructuredOutputConfig $config;
    private ?OutputFormat $outputFormat;

    public function __construct(
        string $class,
        mixed  $instance,
        Schema $schema,
        array  $jsonSchema,
        string $schemaName,
        string $schemaDescription,
        string $toolName,
        string $toolDescription,
        array  $toolCallSchema = [],
        array  $responseFormat = [],
        bool   $useObjectReferences = false,
        ?StructuredOutputConfig $config = null,
        ?OutputFormat $outputFormat = null,
    ) {
        $this->class = $class;
        $this->instance = $instance;
        $this->schema = $schema;
        $this->jsonSchema = $jsonSchema;
        $this->toolCallSchema = $toolCallSchema;
        $this->responseFormat = $responseFormat;
        $this->schemaName = $schemaName;
        $this->schemaDescription = $schemaDescription;
        $this->toolName = $toolName;
        $this->toolDescription = $toolDescription;
        $this->useObjectReferences = $useObjectReferences;
        $this->config = $config ?? new StructuredOutputConfig();
        $this->outputFormat = $outputFormat;
    }

    // ACCESSORS ///////////////////////////////////////////////////////

    public function instanceClass() : string {
        return $this->class;
    }

    public function returnedClass() : string {
        return $this->schema->typeDetails->class ?? '';
    }

    public function instance() : mixed {
        return $this->instance;
    }

    public function schemaName() : string {
        return $this->schemaName ?: ($this->schema()->name() ?: 'default_schema');
    }

    public function schema() : Schema {
        return $this->schema;
    }

    public function config() : StructuredOutputConfig {
        return $this->config;
    }

    /** @return string[] */
    public function getPropertyNames() : array {
        return $this->schema()->getPropertyNames();
    }

    /** @return array<string, mixed> */
    public function getPropertyValues() : array {
        $values = [];
        if (!is_object($this->instance)) {
            return $values;
        }
        foreach ($this->getPropertyNames() as $name) {
            $values[$name] = match(true) {
                /** @phpstan-ignore-next-line */
                isset($this->instance->$name) => $this->instance->$name,
                default => null,
            };
        }
        return $values;
    }

    public function toolName() : string {
        return $this->toolName ?: ($this->config->toolName() ?: 'extract_data');
    }

    public function toolDescription() : string {
        return $this->toolDescription ?: ($this->config->toolDescription() ?: '');
    }

    public function outputFormat(): ?OutputFormat {
        return $this->outputFormat;
    }

    public function shouldReturnArray(): bool {
        return $this->outputFormat?->isArray() ?? false;
    }

    // MUTATORS ////////////////////////////////////////////////////////

    public function withOutputMode(OutputMode $mode) : static {
        return $this->with(
            mode: $mode,
            responseFormat: StructuredOutputSchemaRenderer::responseFormatFor(
                mode: $mode,
                jsonSchema: $this->jsonSchema,
                schemaName: $this->schemaName(),
                toolDescription: $this->toolDescription(),
            ),
        );
    }

    public function withInstance(mixed $instance) : static {
        return $this->with(instance: $instance);
    }

    public function withToolName(string $toolName) : static {
        return $this->with(toolName: $toolName);
    }

    public function withToolDescription(string $toolDescription) : static {
        return $this->with(toolDescription: $toolDescription);
    }

    /** @param array<string, mixed> $values */
    public function withPropertyValues(array $values) : static {
        if (!is_object($this->instance)) {
            return $this;
        }
        $instance = clone $this->instance;
        foreach ($values as $name => $value) {
            if (property_exists($instance, $name)) {
                /** @phpstan-ignore-next-line */
                $instance->$name = $value;
            }
        }
        return $this->with(instance: $instance);
    }

    // CONVERSION //////////////////////////////////////////////////////

    #[\Override]
    public function toJsonSchema() : array {
        // TODO: this can be computed from schema
        return $this->jsonSchema;
    }

    public function jsonSchema() : ?array {
        return $this->toJsonSchema();
    }

    public function toolCallSchema() : array {
        return match($this->config->outputMode()) {
            OutputMode::Tools => $this->toolCallSchema,
            default => [],
        };
    }

    public function responseFormat() : array {
        return $this->responseFormat;
    }

    public function toolChoice() : string|array {
        return match($this->config->outputMode()) {
            OutputMode::Tools => [
                'type' => 'function',
                'function' => [
                    'name' => ($this->toolName() ?: 'extract_data'),
                ]
            ],
            default => [],
        };
    }

    // SERIALIZATION ///////////////////////////////////////////////////

    public function toArray() : array {
        return [
            'class' => $this->class,
            'instance' => is_object($this->instance) ? get_object_vars($this->instance) : [],
            'schema' => $this->schema->toArray(),
            'jsonSchema' => $this->jsonSchema,
            'schemaName' => $this->schemaName,
            'schemaDescription' => $this->schemaDescription,
        ];
    }

    // INTERNAL ////////////////////////////////////////////////////////

    public function with(
        ?OutputMode $mode = null,
        mixed $instance = null,
        ?string $toolName = null,
        ?string $toolDescription = null,
        ?OutputFormat $outputFormat = null,
        ?array $toolCallSchema = null,
        ?array $responseFormat = null,
    ) : static {
        $new = new static(
            class: $this->class,
            instance: $instance ?? $this->instance,
            schema: $this->schema,
            jsonSchema: $this->jsonSchema,
            toolCallSchema: $toolCallSchema ?? $this->toolCallSchema,
            responseFormat: $responseFormat ?? $this->responseFormat,
            schemaName: $this->schemaName,
            schemaDescription: $this->schemaDescription,
            toolName: $toolName ?? $this->toolName,
            toolDescription: $toolDescription ?? $this->toolDescription,
            useObjectReferences: $this->useObjectReferences,
            config: match(true) {
                ($mode === null) => $this->config,
                default => $this->config->withOutputMode($mode),
            },
            outputFormat: $outputFormat ?? $this->outputFormat,
        );
        return $new;
    }

    // tool-call rendering is centralized in StructuredOutputSchemaRenderer
}
