<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanReceiveEvents;
use Cognesy\Instructor\Core\Data\Request;
use Cognesy\Instructor\Core\Data\ResponseModel;
use Cognesy\Instructor\Core\ResponseBuilders\BuildFromArray;
use Cognesy\Instructor\Core\ResponseBuilders\BuildFromClass;
use Cognesy\Instructor\Core\ResponseBuilders\BuildFromInstance;
use Cognesy\Instructor\Core\ResponseBuilders\BuildFromSchema;
use Cognesy\Instructor\Core\ResponseBuilders\BuildFromJsonSchemaProvider;
use Cognesy\Instructor\Core\ResponseBuilders\BuildFromSchemaProvider;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Factories\FunctionCallBuilder;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Utils\SchemaBuilder;


class ResponseModelFactory
{
    private FunctionCallBuilder $functionCallBuilder;
    private SchemaFactory $schemaFactory;
    private SchemaBuilder $schemaBuilder;
    private TypeDetailsFactory $typeDetailsFactory;
    private EventDispatcher $eventDispatcher;

    public function __construct(
        FunctionCallBuilder $functionCallFactory,
        SchemaFactory       $schemaFactory,
        SchemaBuilder       $schemaBuilder,
        TypeDetailsFactory  $typeDetailsFactory,
        EventDispatcher     $eventDispatcher,
    ) {
        $this->functionCallBuilder = $functionCallFactory;
        $this->schemaFactory = $schemaFactory;
        $this->schemaBuilder = $schemaBuilder;
        $this->typeDetailsFactory = $typeDetailsFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

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
            $this->functionCallBuilder,
            $this->schemaFactory,
            $this->schemaBuilder,
            $this->typeDetailsFactory
        );
        $responseModel = $builder->build($requestedModel);
        if ($responseModel instanceof CanReceiveEvents) {
            $this->eventDispatcher->wiretap(fn($event) => $responseModel->onEvent($event));
        }
        return $responseModel;
    }
}