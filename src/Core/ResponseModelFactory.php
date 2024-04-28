<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanReceiveEvents;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Utils\SchemaBuilder;
use Exception;
use InvalidArgumentException;

class ResponseModelFactory
{
    protected TypeDetailsFactory $typeDetailsFactory;
    protected SchemaBuilder $schemaBuilder;

    public function __construct(
        private ToolCallBuilder $toolCallBuilder,
        private SchemaFactory $schemaFactory,
        private EventDispatcher $events,
    ) {
        $this->typeDetailsFactory = new TypeDetailsFactory;
        $this->schemaBuilder = new SchemaBuilder;
    }

    public function fromRequest(Request $request) : ResponseModel {
        $responseModel = $this->fromAny($request->responseModel);
        $responseModel->functionName = $request->functionName;
        $responseModel->functionDescription = $request->functionDescription;
        return $responseModel;
    }

    public function fromAny(mixed $requestedModel) : ResponseModel {
        $responseModel = match(true) {
            $requestedModel instanceof ObjectSchema => $this->fromSchema($requestedModel),
            is_subclass_of($requestedModel, CanProvideJsonSchema::class) => $this->fromJsonSchemaProvider($requestedModel),
            is_subclass_of($requestedModel, CanProvideSchema::class) => $this->fromSchemaProvider($requestedModel),
            is_string($requestedModel) => $this->fromClassString($requestedModel),
            is_array($requestedModel) => $this->fromArray($requestedModel),
            is_object($requestedModel) => $this->fromInstance($requestedModel),
            default => throw new InvalidArgumentException('Unsupported response model type: ' . gettype($requestedModel))
        };
        if ($responseModel instanceof CanReceiveEvents) {
            $this->events->wiretap(fn($event) => $responseModel->onEvent($event));
        }
        return $responseModel;
    }

    private function fromClassString(string $requestedModel) : ResponseModel {
        $class = $requestedModel;
        $instance = new $class;
        $schema = $this->schemaFactory->schema($class);
        $jsonSchema = $schema->toArray($this->toolCallBuilder->onObjectRef(...));
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromArray(array $requestedModel) : ResponseModel {
        $class = $requestedModel['$comment'] ?? null;
        if (empty($class)) {
            throw new Exception('Provided JSON schema must contain $comment field with fully qualified class name');
        }
        $instance = new $class;
        $schema = $this->schemaBuilder->fromArray($requestedModel);
        $jsonSchema = $requestedModel;
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromInstance(mixed $requestedModel) : ResponseModel {
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $schema = $this->schemaFactory->schema($class);
        $jsonSchema = $schema->toArray($this->toolCallBuilder->onObjectRef(...));
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromJsonSchemaProvider(mixed $requestedModel) : ResponseModel {
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = new $class;
        }
        $jsonSchema = $instance->toJsonSchema();
        $schema = $this->schemaBuilder->fromArray($jsonSchema);
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function fromSchema(Schema $requestedModel) : ResponseModel {
        $schema = $requestedModel;
        $class = $schema->type->class;
        $instance = new $class;
        $schema = $requestedModel;
        $jsonSchema = $schema->toArray($this->toolCallBuilder->onObjectRef(...));
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    public function fromSchemaProvider(mixed $requestedModel) : ResponseModel {
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = new $class;
        }
        $schema = $instance->toSchema();
        $jsonSchema = $schema->toArray($this->toolCallBuilder->onObjectRef(...));
        return $this->makeResponseModel($class, $instance, $schema, $jsonSchema);
    }

    private function makeResponseModel(
        string $class,
        object $instance,
        Schema $schema,
        array $jsonSchema,
    ) : ResponseModel {
        return new ResponseModel(
            $class,
            $instance,
            $schema,
            $jsonSchema,
            $this->toolCallBuilder,
        );
    }
}