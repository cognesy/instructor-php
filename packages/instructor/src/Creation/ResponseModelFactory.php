<?php declare(strict_types=1);
namespace Cognesy\Instructor\Creation;

use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\SchemaRendering;
use Cognesy\Instructor\Events\Request\ResponseModelBuildModeSelected;
use Cognesy\Instructor\Events\Request\ResponseModelBuilt;
use Cognesy\Instructor\Events\Request\ResponseModelRequested;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\TypeInfo;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;
use Cognesy\Utils\Str;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseModelFactory
{
    protected StructuredOutputSchemaRenderer $schemaRenderer;
    protected StructuredOutputConfig $config;
    protected EventDispatcherInterface $events;

    public function __construct(
        StructuredOutputSchemaRenderer $schemaRenderer,
        StructuredOutputConfig $config,
        EventDispatcherInterface $events,
    ) {
        $this->schemaRenderer = $schemaRenderer;
        $this->config = $config;
        $this->events = $events;
    }

    public function fromAny(string|array|object $requestedModel, ?OutputFormat $outputFormat = null) : ResponseModel {
        $this->events->dispatch(new ResponseModelRequested(['responseModel' => $requestedModel]));
        $responseModel = $this->buildFrom($requestedModel, $outputFormat);
        $this->events->dispatch(new ResponseModelBuilt(['responseModel' => $responseModel]));
        return $responseModel;
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function buildFrom(string|array|object $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        return match(true) {
            // object and can provide JSON schema
            (is_string($requestedModel) || is_object($requestedModel)) && is_subclass_of($requestedModel, CanProvideJsonSchema::class) => $this->fromJsonSchemaProvider($requestedModel, $outputFormat),
            // object and can provide Schema object
            (is_string($requestedModel) || is_object($requestedModel)) && is_subclass_of($requestedModel, CanProvideSchema::class) => $this->fromSchemaProvider($requestedModel, $outputFormat),
            // object and is instance of Schema (specifically - ObjectSchema)
            $requestedModel instanceof ObjectSchema => $this->fromSchema($requestedModel, $outputFormat),
            //is_subclass_of($requestedModel, HasOutputSchema::class) => $this->fromOutputSchemaProvider($requestedModel),
            // is class-string implementing tool selection handling
            is_string($requestedModel) && is_subclass_of($requestedModel, CanHandleToolSelection::class) => $this->fromToolSelectionProviderClass($requestedModel, $outputFormat),
            // is string - so will be used as class-string
            is_string($requestedModel) => $this->fromClassString($requestedModel, $outputFormat),
            // is array and empty - create a default dynamic structure
            is_array($requestedModel) && empty($requestedModel) => $this->fromClassString($this->config->outputClass(), $outputFormat),
            // is array - so will be used as JSON Schema
            is_array($requestedModel) => $this->fromJsonSchema($requestedModel, $outputFormat),
            // must be object at this point
            default => $this->fromInstance($requestedModel, $outputFormat),
        };
    }

    private function fromClassString(string $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromClassString']));
        $class = $requestedModel;
        $instance = $this->makeInstance($class);
        $schema = $this->schemaRenderer->schemaFactory()->schema($class);
        $rendering = $this->renderSchema($schema);
        $jsonSchema = $rendering->jsonSchema();
        $schemaName = $this->schemaName($requestedModel);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        $renderingPayload = $this->renderingPayloadFor(
            instance: $instance,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            rendering: $rendering,
        );
        return $this->buildResponseModel(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            schemaDescription: $schemaDescription,
            outputFormat: $resolvedOutputFormat,
            toolDefinitions: $renderingPayload['toolDefinitions'],
            responseFormat: $renderingPayload['responseFormat'],
        );
    }

    private function fromJsonSchema(array $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromJsonSchema']));
        $rawClass = $requestedModel['x-php-class'] ?? Structure::class;
        $class = match (true) {
            is_string($rawClass) && $rawClass !== '' => ltrim($rawClass, '\\'),
            default => Structure::class,
        };
        $schema = $this->schemaRenderer->schemaFromJsonSchema($requestedModel);
        $schemaName = $this->schemaName($requestedModel);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $schema = SchemaFactory::withMetadata(
            $schema,
            name: $schemaName,
            description: $schemaDescription,
        );
        $isStructureSchema = match (true) {
            $class === \stdClass::class => true,
            $class === Structure::class => true,
            is_subclass_of($class, Structure::class) => true,
            default => false,
        };
        $resolvedClass = match (true) {
            $isStructureSchema => Structure::class,
            default => $class,
        };
        $instance = match (true) {
            $isStructureSchema => Structure::fromSchema($schema),
            default => $this->makeInstance($class),
        };
        $jsonSchema = $requestedModel;
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        $renderingPayload = $this->renderingPayloadFor(
            instance: $instance,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
        );
        return $this->buildResponseModel(
            class: $resolvedClass,
            instance: $instance,
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            schemaDescription: $schemaDescription,
            outputFormat: $resolvedOutputFormat,
            toolDefinitions: $renderingPayload['toolDefinitions'],
            responseFormat: $renderingPayload['responseFormat'],
        );
    }

    private function fromInstance(object $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromInstance']));
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $schema = $this->schemaRenderer->schemaFactory()->schema($class);
        $rendering = $this->renderSchema($schema);
        $jsonSchema = $rendering->jsonSchema();
        $schemaName = $this->schemaName($requestedModel);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        $renderingPayload = $this->renderingPayloadFor(
            instance: $instance,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            rendering: $rendering,
        );
        return $this->buildResponseModel(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            schemaDescription: $schemaDescription,
            outputFormat: $resolvedOutputFormat,
            toolDefinitions: $renderingPayload['toolDefinitions'],
            responseFormat: $renderingPayload['responseFormat'],
        );
    }

    private function fromJsonSchemaProvider(object|string $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromJsonSchemaProvider']));
        [$class, $instance] = $this->resolveClassAndInstance($requestedModel);
        $jsonSchema = $instance->toJsonSchema();
        $schema = $this->schemaRenderer->schemaFromJsonSchema($jsonSchema);
        $schemaName = $this->schemaName($requestedModel);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        $renderingPayload = $this->renderingPayloadFor(
            instance: $instance,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
        );
        return $this->buildResponseModel(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            schemaDescription: $schemaDescription,
            outputFormat: $resolvedOutputFormat,
            toolDefinitions: $renderingPayload['toolDefinitions'],
            responseFormat: $renderingPayload['responseFormat'],
        );
    }

    private function fromSchemaProvider(object|string $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromSchemaProvider']));
        [$class, $instance] = $this->resolveClassAndInstance($requestedModel);
        $schema = $instance->toSchema();
        $rendering = $this->renderSchema($schema);
        $jsonSchema = $rendering->jsonSchema();
        $schemaName = $this->schemaName($schema);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        $renderingPayload = $this->renderingPayloadFor(
            instance: $instance,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            rendering: $rendering,
        );
        return $this->buildResponseModel(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            schemaDescription: $schemaDescription,
            outputFormat: $resolvedOutputFormat,
            toolDefinitions: $renderingPayload['toolDefinitions'],
            responseFormat: $renderingPayload['responseFormat'],
        );
    }

    private function fromSchema(Schema $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromSchema']));
        $schema = $requestedModel;
        $schemaClass = TypeInfo::className($schema->type);
        $isStructureSchema = $schema instanceof ObjectSchema && match (true) {
            $schemaClass === null => true,
            $schemaClass === \stdClass::class => true,
            $schemaClass === Structure::class => true,
            is_subclass_of($schemaClass, Structure::class) => true,
            default => false,
        };
        [$class, $instance] = match (true) {
            $isStructureSchema => [Structure::class, Structure::fromSchema($schema)],
            $schemaClass === null => throw new InvalidArgumentException('Schema must have a class to create ResponseModel'),
            default => [$schemaClass, $this->makeInstance($schemaClass)],
        };
        $rendering = $this->renderSchema($schema);
        $jsonSchema = $rendering->jsonSchema();
        $schemaName = $this->schemaName($requestedModel);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        $renderingPayload = $this->renderingPayloadFor(
            instance: $instance,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            rendering: $rendering,
        );
        return $this->buildResponseModel(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            schemaDescription: $schemaDescription,
            outputFormat: $resolvedOutputFormat,
            toolDefinitions: $renderingPayload['toolDefinitions'],
            responseFormat: $renderingPayload['responseFormat'],
        );
    }

    /**
     * @param class-string<CanHandleToolSelection> $requestedModel
     */
    private function fromToolSelectionProviderClass(string $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        [, $instance] = $this->resolveClassAndInstance($requestedModel);
        assert($instance instanceof CanHandleToolSelection);
        return $this->fromToolSelectionProvider($instance, $outputFormat);
    }

    private function fromToolSelectionProvider(CanHandleToolSelection $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromToolSelectionProvider']));
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $jsonSchema = $instance->toJsonSchema();
        $schema = $instance->toSchema();
        $schemaName = $this->schemaName($schema);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        $renderingPayload = $this->renderingPayloadFor(
            instance: $instance,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
        );
        return $this->buildResponseModel(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            schemaDescription: $schemaDescription,
            outputFormat: $resolvedOutputFormat,
            toolDefinitions: $renderingPayload['toolDefinitions'],
            responseFormat: $renderingPayload['responseFormat'],
        );
    }

    private function schemaName(string|array|object $requestedSchema) : string {
        $name = match(true) {
            is_string($requestedSchema) => $requestedSchema,
            is_array($requestedSchema) => $requestedSchema['name'] ?? $requestedSchema['x-title'] ?? null,
            method_exists($requestedSchema, 'name') => $requestedSchema->name(),
            method_exists($requestedSchema, 'toSchema') => $requestedSchema->toSchema()->name(),
            default => 'default_schema',
        };
        $name = $name ?: $this->config->schemaName() ?: 'default_schema';
        if (Str::startsWith($name, '\\')) {
            $name = substr($name, 1);
        }
        $name = str_replace('\\', '_', $name);
        return $name;
    }

    private function schemaDescription(string|array|object $requestedSchema) : string {
        $resolved = match(true) {
            is_string($requestedSchema) => '',
            is_array($requestedSchema) => $requestedSchema['description'] ?? '',
            $requestedSchema instanceof Schema => $requestedSchema->description(),
            //method_exists($requestedSchema, 'toSchema') => $requestedSchema->toSchema()->description(),
            default => '',
        };
        return $resolved ?: $this->config->schemaDescription() ?: '';
    }

    private function makeInstance(string $class) : object {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Class $class does not exist.");
        }
        $reflection = new \ReflectionClass($class);
        $instance = $reflection->newInstanceWithoutConstructor();
        if (!is_object($instance)) {
            throw new InvalidArgumentException("Class $class does not instantiate an object.");
        }
        return $instance;
    }

    private function buildResponseModel(
        string $class,
        object $instance,
        Schema $schema,
        array $jsonSchema,
        string $schemaName,
        string $schemaDescription,
        ?OutputFormat $outputFormat,
        ToolDefinitions $toolDefinitions,
        ResponseFormat $responseFormat,
    ) : ResponseModel {
        return new ResponseModel(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            schemaDescription: $schemaDescription,
            toolName: $this->config->toolName(),
            toolDescription: $this->config->toolDescription(),
            toolDefinitions: $toolDefinitions,
            responseFormat: $responseFormat,
            useObjectReferences: $this->config->useObjectReferences(),
            config: $this->config,
            outputFormat: $outputFormat,
        );
    }

    /**
     * @return array{0: string, 1: object}
     */
    private function resolveClassAndInstance(object|string $requestedModel) : array {
        if (is_object($requestedModel)) {
            return [get_class($requestedModel), $requestedModel];
        }
        return [$requestedModel, $this->makeProviderInstance($requestedModel)];
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

    private function renderSchema(Schema $schema) : SchemaRendering {
        return $this->schemaRenderer->renderFromSchema($schema);
    }

    private function renderResponseFormat(array $jsonSchema, string $schemaName) : ResponseFormat {
        return $this->schemaRenderer->renderResponseFormat(
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            toolDescription: $this->config->toolDescription(),
        );
    }

    private function toolDefinitionsForInstance(
        object $instance,
        array $jsonSchema,
        ?SchemaRendering $rendering = null,
    ) : ToolDefinitions {
        return match(true) {
            $instance instanceof CanHandleToolSelection => $instance->toToolDefinitions(),
            $rendering !== null => $rendering->toolDefinitions(),
            default => $this->schemaRenderer->renderToolCallSchema($jsonSchema),
        };
    }

    /**
     * @return array{toolDefinitions: ToolDefinitions, responseFormat: ResponseFormat}
     */
    private function renderingPayloadFor(
        object $instance,
        array $jsonSchema,
        string $schemaName,
        ?SchemaRendering $rendering = null,
    ) : array {
        return [
            'toolDefinitions' => $this->toolDefinitionsForInstance($instance, $jsonSchema, $rendering),
            'responseFormat' => $this->renderResponseFormat($jsonSchema, $schemaName),
        ];
    }

    private function resolveOutputFormat(?OutputFormat $outputFormat, Schema $schema) : ?OutputFormat {
        return $outputFormat;
    }
}
