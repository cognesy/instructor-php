<?php declare(strict_types=1);
namespace Cognesy\Instructor\Creation;

use Cognesy\Dynamic\Structure;
use Cognesy\Dynamic\StructureFactory;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\Request\ResponseModelBuildModeSelected;
use Cognesy\Instructor\Events\Request\ResponseModelBuilt;
use Cognesy\Instructor\Events\Request\ResponseModelRequested;
use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Factories\JsonSchemaToSchema;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Schema\Visitors\SchemaToJsonSchema;
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

    protected JsonSchemaToSchema $schemaConverter;

    public function __construct(
        ToolCallBuilder $toolCallBuilder,
        SchemaFactory $schemaFactory,
        StructuredOutputConfig $config,
        EventDispatcherInterface $events,
    ) {
        $this->toolCallBuilder = $toolCallBuilder;
        $this->schemaFactory = $schemaFactory;
        $this->config = $config;
        $this->schemaConverter = new JsonSchemaToSchema(
            defaultToolName: $config->toolName(),
            defaultToolDescription: $config->toolDescription(),
        );
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
        $schema = $this->schemaFactory->schema($class);
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        $schemaName = $this->schemaName($requestedModel);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName, $schemaDescription, $resolvedOutputFormat);
    }

    private function fromJsonSchema(array $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromJsonSchema']));
        $class = $requestedModel['x-php-class'] ?? '\Cognesy\Dynamic\Structure';
        $schema = $this->schemaConverter->fromJsonSchema($requestedModel);
        $schemaName = $this->schemaName($requestedModel);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $schema = $schema
            ->withName($schemaName)
            ->withDescription($schemaDescription);
        $instance = match (true) {
            $class === Structure::class,
            is_subclass_of($class, Structure::class) => StructureFactory::fromSchema(
                $schemaName,
                $schema,
                $schemaDescription,
            ),
            default => $this->makeInstance($class),
        };
        $jsonSchema = $requestedModel;
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName, $schemaDescription, $resolvedOutputFormat);
    }

    private function fromInstance(object $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromInstance']));
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $schema = $this->schemaFactory->schema($class);
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        $schemaName = $this->schemaName($requestedModel);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName, $schemaDescription, $resolvedOutputFormat);
    }

    private function fromJsonSchemaProvider(object|string $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromJsonSchemaProvider']));
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = $this->makeInstance($class);
        }
        $jsonSchema = $instance->toJsonSchema();
        $schema = $this->schemaConverter->fromJsonSchema($jsonSchema);
        $schemaName = $this->schemaName($requestedModel);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName, $schemaDescription, $resolvedOutputFormat);
    }

    private function fromSchemaProvider(object|string $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromSchemaProvider']));
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = $this->makeInstance($class);
        }
        $schema = $instance->toSchema();
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        $schemaName = $this->schemaName($schema);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName, $schemaDescription, $resolvedOutputFormat);
    }

    private function fromSchema(Schema $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromSchema']));
        $schema = $requestedModel;
        $class = $schema->typeDetails->class ?? throw new InvalidArgumentException('Schema must have a class to create ResponseModel');
        $instance = $this->makeInstance($class);
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        $schemaName = $this->schemaName($requestedModel);
        $schemaDescription = $this->schemaDescription($requestedModel);
        $resolvedOutputFormat = $this->resolveOutputFormat($outputFormat, $schema);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName, $schemaDescription, $resolvedOutputFormat);
    }

    /**
     * @param class-string<CanHandleToolSelection> $requestedModel
     */
    private function fromToolSelectionProviderClass(string $requestedModel, ?OutputFormat $outputFormat) : ResponseModel {
        $instance = $this->makeInstance($requestedModel);
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
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema, $schemaName, $schemaDescription, $resolvedOutputFormat);
    }

    private function schemaName(string|array|object $requestedSchema) : string {
        $name = match(true) {
            is_string($requestedSchema) => $requestedSchema,
            is_array($requestedSchema) => $requestedSchema['name'] ?? $requestedSchema['x-title'] ?? null,
            method_exists($requestedSchema, 'name') => $requestedSchema->name(),
            method_exists($requestedSchema, 'toSchema') => $requestedSchema->toSchema()->typeDetails->name,
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

    private function makeResponseModel(
        string $class,
        object $instance,
        Schema $schema,
        array $jsonSchema,
        string $schemaName,
        string $schemaDescription,
        ?OutputFormat $outputFormat,
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
            useObjectReferences: $this->config->useObjectReferences(),
            config: $this->config,
            outputFormat: $outputFormat,
        );
    }

    private function resolveOutputFormat(?OutputFormat $outputFormat, Schema $schema) : ?OutputFormat {
        if ($outputFormat !== null) {
            return $outputFormat;
        }
        $returnedClass = $schema->typeDetails->class ?? '';
        return $returnedClass === '' ? OutputFormat::array() : null;
    }
}
