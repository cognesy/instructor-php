<?php
namespace Cognesy\Instructor\Core\ResponseModel;

use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanReceiveEvents;
use Cognesy\Instructor\Core\ResponseModel\Builders\BuildFromArray;
use Cognesy\Instructor\Core\ResponseModel\Builders\BuildFromClass;
use Cognesy\Instructor\Core\ResponseModel\Builders\BuildFromInstance;
use Cognesy\Instructor\Core\ResponseModel\Builders\BuildFromJsonSchemaProvider;
use Cognesy\Instructor\Core\ResponseModel\Builders\BuildFromSchema;
use Cognesy\Instructor\Core\ResponseModel\Builders\BuildFromSchemaProvider;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\ToolCallBuilder;

class ResponseModelFactory
{
    public function __construct(
        private ToolCallBuilder $toolCallBuilder,
        private SchemaFactory $schemaFactory,
        private EventDispatcher $events,
    ) {}

    public function fromRequest(Request $request) : ResponseModel {
        return $this->fromAny($request->responseModel);
    }

    public function fromAny(mixed $requestedModel) : ResponseModel {
        $builderClass = match (true) {
            $requestedModel instanceof ObjectSchema => BuildFromSchema::class,
            is_subclass_of($requestedModel, CanProvideJsonSchema::class) => BuildFromJsonSchemaProvider::class,
            is_subclass_of($requestedModel, CanProvideSchema::class) => BuildFromSchemaProvider::class,
            is_string($requestedModel) => BuildFromClass::class,
            is_array($requestedModel) => BuildFromArray::class,
            is_object($requestedModel) => BuildFromInstance::class,
            default => throw new \InvalidArgumentException('Unsupported response model type: ' . gettype($requestedModel))
        };
        $builder = new $builderClass(
            $this->toolCallBuilder,
            $this->schemaFactory,
        );
        $responseModel = $builder->build($requestedModel);
        if ($responseModel instanceof CanReceiveEvents) {
            $this->events->wiretap(fn($event) => $responseModel->onEvent($event));
        }
        return $responseModel;
    }
}