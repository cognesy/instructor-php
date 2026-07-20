<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation\ResponseModel;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\SchemaRendering;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\TypeInfo;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Shared machinery for the per-input-shape resolvers: name/description
 * derivation, instance creation, rendering payloads, and final ResponseModel
 * assembly. Resolvers own only what differs per input shape.
 */
final class ResolutionSupport
{
    public function __construct(
        private readonly StructuredOutputSchemaRenderer $schemaRenderer,
        private readonly StructuredOutputConfig $config,
        private readonly EventDispatcherInterface $events,
    ) {}

    public function schemaRenderer(): StructuredOutputSchemaRenderer {
        return $this->schemaRenderer;
    }

    public function config(): StructuredOutputConfig {
        return $this->config;
    }

    public function events(): EventDispatcherInterface {
        return $this->events;
    }

    public function schemaName(string|array|object $requestedSchema): string {
        $name = match (true) {
            is_string($requestedSchema) => $requestedSchema,
            is_array($requestedSchema) => $requestedSchema['name'] ?? $requestedSchema['x-title'] ?? null,
            method_exists($requestedSchema, 'name') => $requestedSchema->name(),
            method_exists($requestedSchema, 'toSchema') => $requestedSchema->toSchema()->name(),
            default => 'default_schema',
        };
        $name = $name ?: $this->config->schemaName() ?: 'default_schema';
        return str_replace('\\', '_', ltrim($name, '\\'));
    }

    public function schemaDescription(string|array|object $requestedSchema): string {
        $resolved = match (true) {
            is_string($requestedSchema) => '',
            is_array($requestedSchema) => $requestedSchema['description'] ?? '',
            $requestedSchema instanceof Schema => $requestedSchema->description(),
            default => '',
        };
        return $resolved ?: $this->config->schemaDescription() ?: '';
    }

    /**
     * @return array{0: string, 1: object}
     */
    public function resolveClassAndInstance(object|string $requestedModel): array {
        if (is_object($requestedModel)) {
            return [get_class($requestedModel), $requestedModel];
        }
        return [$requestedModel, $this->makeProviderInstance($requestedModel)];
    }

    public function renderSchema(Schema $schema): SchemaRendering {
        return $this->schemaRenderer->renderFromSchema($schema);
    }

    /**
     * Assembles the final ResponseModel from resolver-derived parts plus the
     * shared rendering payload (tool definitions + response format).
     */
    public function assemble(
        ?object $instance,
        Schema $schema,
        array $jsonSchema,
        string $schemaName,
        string $schemaDescription,
        ?OutputFormat $outputFormat,
        ?SchemaRendering $rendering = null,
    ): ResponseModel {
        return new ResponseModel(
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            schemaDescription: $schemaDescription,
            toolName: $this->config->toolName(),
            toolDescription: $this->config->toolDescription(),
            toolDefinitions: $this->toolDefinitionsForInstance($instance, $jsonSchema, $rendering),
            responseFormat: $this->renderResponseFormat($jsonSchema, $schemaName),
            config: $this->config,
            outputFormat: $this->resolveOutputFormat(
                $outputFormat,
                TypeInfo::className($schema->type) ?? '',
                $instance,
            ),
        );
    }

    private function resolveOutputFormat(
        ?OutputFormat $requested,
        string $schemaClass,
        ?object $instance,
    ): OutputFormat {
        return match (true) {
            $requested !== null => $requested,
            $instance instanceof CanDeserializeSelf => OutputFormat::selfDeserializing($instance),
            $schemaClass !== '' => $this->classOutputFormat($schemaClass),
            $this->config->defaultToStdClass() => OutputFormat::stdClass(),
            default => OutputFormat::array(),
        };
    }

    private function classOutputFormat(string $class): OutputFormat {
        $class = ltrim($class, '\\');
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Output class does not exist: {$class}");
        }
        return OutputFormat::instanceOf($class);
    }

    private function makeProviderInstance(string $class): object {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Class $class does not exist.");
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new InvalidArgumentException(
                "Schema provider class {$class} requires constructor arguments. ".
                'Pass a provider instance instead of class-string.'
            );
        }

        try {
            return $reflection->newInstance();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                "Failed to instantiate schema provider class {$class}: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    private function renderResponseFormat(array $jsonSchema, string $schemaName): ResponseFormat {
        return $this->schemaRenderer->renderResponseFormat(
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            toolDescription: $this->config->toolDescription(),
        );
    }

    private function toolDefinitionsForInstance(
        ?object $instance,
        array $jsonSchema,
        ?SchemaRendering $rendering,
    ): ToolDefinitions {
        return match (true) {
            $instance instanceof CanHandleToolSelection => $instance->toToolDefinitions(),
            $rendering !== null => $rendering->toolDefinitions(),
            default => $this->schemaRenderer->renderToolCallSchema($jsonSchema),
        };
    }
}
