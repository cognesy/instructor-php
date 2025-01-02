<?php
namespace Cognesy\Instructor\Features\Core;

use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanReceiveEvents;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Request\ResponseModelBuildModeSelected;
use Cognesy\Instructor\Events\Request\ResponseModelBuilt;
use Cognesy\Instructor\Events\Request\ResponseModelRequested;
use Cognesy\Instructor\Experimental\Module\Signature\Contracts\HasOutputSchema;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Features\Core\Data\Request;
use Cognesy\Instructor\Features\Core\Data\ResponseModel;
use Cognesy\Instructor\Features\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Features\Schema\Factories\JsonSchemaToSchema;
use Cognesy\Instructor\Features\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Features\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Features\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Features\Schema\Visitors\SchemaToJsonSchema;
use InvalidArgumentException;

class ResponseModelFactory
{
    protected TypeDetailsFactory $typeDetailsFactory;
    protected JsonSchemaToSchema $schemaConverter;

    public function __construct(
        private ToolCallBuilder $toolCallBuilder,
        private SchemaFactory $schemaFactory,
        private EventDispatcher $events,
    ) {
        $this->typeDetailsFactory = new TypeDetailsFactory;
        $this->schemaConverter = new JsonSchemaToSchema;
    }

    public function fromRequest(Request $request) : ResponseModel {
        return $this->fromAny(
            $request->requestedSchema(),
            $request->toolName(),
            $request->toolDescription()
        );
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
            $this->events->wiretap(fn($event) => $responseModel->onEvent($event));
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
            is_subclass_of($requestedModel, CanProvideJsonSchema::class) => $this->fromJsonSchemaProvider($requestedModel, $toolName, $toolDescription),
            is_subclass_of($requestedModel, CanProvideSchema::class) => $this->fromSchemaProvider($requestedModel),
            $requestedModel instanceof ObjectSchema => $this->fromSchema($requestedModel),
            is_subclass_of($requestedModel, HasOutputSchema::class) => $this->fromOutputSchemaProvider($requestedModel),
            is_subclass_of($requestedModel, CanHandleToolSelection::class) => $this->fromToolSelectionProvider($requestedModel),
            is_string($requestedModel) => $this->fromClassString($requestedModel),
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
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromJsonSchema(array $requestedModel, string $name = '', string $description = '') : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromJsonSchema'));
        $class = $requestedModel['x-php-class'] ?? Structure::class;
        $instance = new $class;
        $schema = $this->schemaConverter->fromJsonSchema($requestedModel, $name, $description);
        $jsonSchema = $requestedModel;
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromInstance(mixed $requestedModel) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromInstance'));
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $schema = $this->schemaFactory->schema($class);
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
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
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
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
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromSchema(Schema $requestedModel) : ResponseModel {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromSchema'));
        $schema = $requestedModel;
        $class = $schema->typeDetails->class;
        $instance = new $class;
        $jsonSchema = (new SchemaToJsonSchema)->toArray($schema, $this->toolCallBuilder->onObjectRef(...));
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromToolSelectionProvider(CanHandleToolSelection $requestedModel) {
        $this->events->dispatch(new ResponseModelBuildModeSelected(mode: 'fromToolSelectionProvider'));
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $jsonSchema = $instance->toJsonSchema();
        $schema = $instance->toSchema();
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
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
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

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
}
