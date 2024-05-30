<?php
namespace Cognesy\Instructor\Core\Factories;

use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanReceiveEvents;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Request\ResponseModelBuilt;
use Cognesy\Instructor\Events\Request\ResponseModelRequested;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\HasOutputSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\SchemaConverter;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Visitors\SchemaToJsonSchema;
use InvalidArgumentException;

class ResponseModelFactory
{
    protected TypeDetailsFactory $typeDetailsFactory;
    protected SchemaConverter $schemaConverter;

    public function __construct(
        private ToolCallBuilder $toolCallBuilder,
        private SchemaFactory $schemaFactory,
        private EventDispatcher $events,
    ) {
        $this->typeDetailsFactory = new TypeDetailsFactory;
        $this->schemaConverter = new SchemaConverter;
    }

    public function fromRequest(Request $request) : ResponseModel {
        return $this->fromAny($request->requestedSchema(), $request->toolName(), $request->toolDescription());
    }

    public function fromAny(
        string|array|object $requestedModel,
        string $toolName = '',
        string $toolDescription = '',
    ) : ResponseModel {
        $this->events->dispatch(new ResponseModelRequested($requestedModel));

        // determine the type of the requested model and build it
        $responseModel = match(true) {
            $requestedModel instanceof ObjectSchema => $this->fromSchema($requestedModel),
            is_subclass_of($requestedModel, CanHandleToolSelection::class) => $this->fromToolSelectionProvider($requestedModel),
            is_subclass_of($requestedModel, CanProvideJsonSchema::class) => $this->fromJsonSchemaProvider($requestedModel, $toolName, $toolDescription),
            is_subclass_of($requestedModel, HasOutputSchema::class) => $this->fromOutputSchemaProvider($requestedModel),
            is_subclass_of($requestedModel, CanProvideSchema::class) => $this->fromSchemaProvider($requestedModel),
            is_string($requestedModel) => $this->fromClassString($requestedModel),
            is_array($requestedModel) => $this->fromJsonSchema($requestedModel, $toolName, $toolDescription),
            is_object($requestedModel) => $this->fromInstance($requestedModel),
            default => throw new InvalidArgumentException('Unsupported response model type: ' . gettype($requestedModel))
        };

        // connect response model to event dispatcher - if it can receive events
        if ($responseModel instanceof CanReceiveEvents) {
            $this->events->wiretap(fn($event) => $responseModel->onEvent($event));
        }

        $responseModel->withToolName($toolName);
        $responseModel->withToolDescription($toolDescription);
        $this->events->dispatch(new ResponseModelBuilt($responseModel));
        return $responseModel;
    }

    public function getSignature(mixed $requestedModel) : string {
        $type = match(true) {
            $requestedModel instanceof ObjectSchema => 'schema',
            is_subclass_of($requestedModel, CanProvideJsonSchema::class) => 'json-schema-provider',
            is_subclass_of($requestedModel, CanProvideSchema::class) => 'schema-provider',
            is_string($requestedModel) => 'class-string',
            is_array($requestedModel) => 'json-schema',
            is_object($requestedModel) => 'instance',
            default => null,
        };
        $keyType = match($type) {
            'schema' => $requestedModel->typeDetails->class,
            'json-schema-provider' => get_class($requestedModel),
            'schema-provider' => get_class($requestedModel),
            'class-string' => $requestedModel,
            'json-schema' => $requestedModel['$comment'] ?? Structure::class,
            'instance' => get_class($requestedModel),
            default => null,
        };
        return $type;
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////////////

    private function makeResponseModel(
        string $class,
        object $instance,
        Schema $schema,
        array $jsonSchema,
    ) : ResponseModel {
        return new ResponseModel(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $jsonSchema,
            toolCallBuilder: $this->toolCallBuilder,
        );
    }

    private function fromClassString(string $requestedModel) : ResponseModel {
        $class = $requestedModel;
        $instance = new $class;
        $schema = $this->schemaFactory->schema($class);
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromJsonSchema(array $requestedModel, string $name = '', string $description = '') : ResponseModel {
        $class = $requestedModel['$comment'] ?? Structure::class;
        $instance = new $class;
        $schema = $this->schemaConverter->fromJsonSchema($requestedModel, $name, $description);
        $jsonSchema = $requestedModel;
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromInstance(mixed $requestedModel) : ResponseModel {
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $schema = $this->schemaFactory->schema($class);
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromJsonSchemaProvider(mixed $requestedModel, string $name = '', string $description = '') : ResponseModel {
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = new $class;
        }
        $jsonSchema = $instance->toJsonSchema();
        $schema = $this->schemaConverter->fromJsonSchema($jsonSchema, $name, $description);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromSchemaProvider(mixed $requestedModel) : ResponseModel {
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = new $class;
        }
        $schema = $instance->toSchema();
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromSchema(Schema $requestedModel) : ResponseModel {
        $schema = $requestedModel;
        $class = $schema->typeDetails->class;
        $instance = new $class;
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromToolSelectionProvider(CanHandleToolSelection $requestedModel) {
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $jsonSchema = $instance->toJsonSchema();
        $schema = $instance->toSchema();
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromOutputSchemaProvider(mixed $requestedModel) {
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = new $class;
        }
        $schema = $instance->toOutputSchema();
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }
}
