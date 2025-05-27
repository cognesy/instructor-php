<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Instructor\Events\Request\ResponseModelBuildModeSelected;
use Cognesy\Instructor\Events\Request\ResponseModelBuilt;
use Cognesy\Instructor\Events\Request\ResponseModelRequested;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Factories\JsonSchemaToSchema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Schema\Factories\TypeDetailsFactory;
use Cognesy\Schema\Visitors\SchemaToJsonSchema;
use Cognesy\Utils\Events\Contracts\CanReceiveEvents;
use Cognesy\Utils\Events\Contracts\EventListenerInterface;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;
use Cognesy\Utils\Str;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseModelFactory
{
    protected ToolCallBuilder $toolCallBuilder;
    protected SchemaFactory $schemaFactory;
    protected StructuredOutputConfig $config;
    protected EventDispatcherInterface $events;
    protected EventListenerInterface $listener;

    protected TypeDetailsFactory $typeDetailsFactory;
    protected JsonSchemaToSchema $schemaConverter;

    public function __construct(
        ToolCallBuilder $toolCallBuilder,
        SchemaFactory $schemaFactory,
        StructuredOutputConfig $config,
        EventDispatcherInterface $events,
        EventListenerInterface $listener,
    ) {
        $this->toolCallBuilder = $toolCallBuilder;
        $this->schemaFactory = $schemaFactory;
        $this->typeDetailsFactory = new TypeDetailsFactory;
        $this->config = $config;
        $this->schemaConverter = new JsonSchemaToSchema(
            defaultToolName: $config->toolName(),
            defaultToolDescription: $config->toolDescription(),
            defaultOutputClass: $config->defaultOutputClass(),
        );
        $this->events = $events ?? new EventDispatcher();
        $this->listener = $listener ?? $this->events;
    }

    public function fromAny(
        string|array|object $requestedModel,
        string $toolName = '',
        string $toolDescription = '',
    ) : ResponseModel {
        $this->events->dispatch(new ResponseModelRequested($requestedModel));

        // determine the type of the requested model and build it
        $responseModel = $this->buildFrom($requestedModel, $toolName, $toolDescription);

        // connect response model to event dispatcher - if it can receive events
        if ($responseModel instanceof CanReceiveEvents) {
            $this->listener->wiretap(fn($event) => $responseModel->onEvent($event));
        }

        $responseModel->withToolName($toolName);
        $responseModel->withToolDescription($toolDescription);
        $this->events->dispatch(new ResponseModelBuilt($responseModel));
        return $responseModel;
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function buildFrom(
        string|array|object $requestedModel,
        string $toolName = '',
        string $toolDescription = '',
    ) : ResponseModel {
        return match(true) {
            // object and can provide JSON schema
            is_subclass_of($requestedModel, CanProvideJsonSchema::class) => $this->fromJsonSchemaProvider($requestedModel, $toolName, $toolDescription),
            // object and can provide Schema object
            is_subclass_of($requestedModel, CanProvideSchema::class) => $this->fromSchemaProvider($requestedModel),
            // object and is instance of Schema (specifically - ObjectSchema)
            $requestedModel instanceof ObjectSchema => $this->fromSchema($requestedModel),
            //is_subclass_of($requestedModel, HasOutputSchema::class) => $this->fromOutputSchemaProvider($requestedModel),
            // is object and can provide OpenAI tool call
            is_subclass_of($requestedModel, CanHandleToolSelection::class) => $this->fromToolSelectionProvider($requestedModel),
            // is string - so will be used as class-string
            is_string($requestedModel) => $this->fromClassString($requestedModel),
            // is array - so will be used as JSON Schema
            is_array($requestedModel) => $this->fromJsonSchema($requestedModel, $toolName, $toolDescription),
            is_object($requestedModel) => $this->fromInstance($requestedModel),
            default => throw new InvalidArgumentException('Unsupported response model type: ' . gettype($requestedModel))
        };
    }

    private function fromClassString(string $requestedModel) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromClassString'));
        $class = $requestedModel;
        $instance = new $class;
        $schema = $this->schemaFactory->schema($class);
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        $schemaName = $this->schemaName($requestedModel);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName);
    }

    private function fromJsonSchema(array $requestedModel, string $name = '', string $description = '') : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromJsonSchema'));
        $class = $requestedModel['x-php-class'] ?? Structure::class;
        $instance = new $class;
        $schema = $this->schemaConverter->fromJsonSchema($requestedModel, $name, $description);
        $jsonSchema = $requestedModel;
        $schemaName = $name ?: $this->schemaName($requestedModel);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName);
    }

    private function fromInstance(mixed $requestedModel) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromInstance'));
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $schema = $this->schemaFactory->schema($class);
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        $schemaName = $this->schemaName($requestedModel);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName);
    }

    private function fromJsonSchemaProvider(mixed $requestedModel, string $name = '', string $description = '') : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromJsonSchemaProvider'));
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = new $class;
        }
        $jsonSchema = $instance->toJsonSchema();
        $schema = $this->schemaConverter->fromJsonSchema($jsonSchema, $name, $description);
        $schemaName = $name ?: $this->schemaName($schema);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName);
    }

    private function fromSchemaProvider(mixed $requestedModel) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromSchemaProvider'));
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = new $class;
        }
        $schema = $instance->toSchema();
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        $schemaName = $this->schemaName($schema);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName);
    }

    private function fromSchema(Schema $requestedModel) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromSchema'));
        $schema = $requestedModel;
        $class = $schema->typeDetails->class;
        $instance = new $class;
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        $schemaName = $this->schemaName($requestedModel);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName);
    }

    private function fromToolSelectionProvider(CanHandleToolSelection $requestedModel) {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromToolSelectionProvider'));
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $jsonSchema = $instance->toJsonSchema();
        $schema = $instance->toSchema();
        $schemaName = $this->schemaName($schema);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName);
    }

    private function fromOutputSchemaProvider(mixed $requestedModel) {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromOutputSchemaProvider'));
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = new $class;
        }
        $schema = $instance->toOutputSchema();
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        $schemaName = $this->schemaName($schema);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName);
    }

    private function schemaName(string|array|object $requestedSchema) : string {
        $name = match(true) {
            is_string($requestedSchema) => $requestedSchema,
            is_array($requestedSchema) => $requestedSchema['name'] ?? $this->config->schemaName(),
            is_object($requestedSchema) && method_exists($requestedSchema, 'name') => $requestedSchema->name(),
            is_object($requestedSchema) && method_exists($requestedSchema, 'toSchema') => $requestedSchema->toSchema()->typeDetails->name,
            is_object($requestedSchema) => get_class($requestedSchema),
            default => 'default_schema',
        };
        if (Str::startsWith($name, '\\')) {
            $name = substr($name, 1);
        }
        $name = str_replace('\\', '_', $name);
        return $name;
    }

    private function makeResponseModel(
        string $class,
        object $instance,
        Schema $schema,
        array $jsonSchema,
        string $schemaName,
    ) : ResponseModel {
        return new ResponseModel(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $jsonSchema,
            schemaName: $schemaName,
            toolCallBuilder: $this->toolCallBuilder,
        );
    }
}
