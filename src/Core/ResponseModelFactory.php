<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Contracts\CanReceiveEvents;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Factories\FunctionCallBuilder;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Utils\SchemaBuilder;
use Exception;


class ResponseModelFactory
{
    private FunctionCallBuilder $functionCallFactory;
    private SchemaFactory $schemaFactory;
    private SchemaBuilder $schemaBuilder;

    private string $functionName = 'extract_data';
    private string $functionDescription = 'Extract data from provided content';

    public function __construct(
        FunctionCallBuilder $functionCallFactory,
        SchemaFactory       $schemaFactory,
        SchemaBuilder       $schemaBuilder
    ) {
        $this->functionCallFactory = $functionCallFactory;
        $this->schemaFactory = $schemaFactory;
        $this->schemaBuilder = $schemaBuilder;
    }

    public function from(mixed $requestedModel) : ResponseModel {
        return match (true) {
            $requestedModel instanceof ObjectSchema => $this->makeSchemaResponseModel($requestedModel),
            is_subclass_of($requestedModel, CanProvideJsonSchema::class) => $this->makeSchemaProviderResponseModel($requestedModel),
            is_string($requestedModel) => $this->makeStringResponseModel($requestedModel),
            is_array($requestedModel) => $this->makeArrayResponseModel($requestedModel),
            default => $this->makeInstanceResponseModel($requestedModel),
        };
    }

    private function makeStringResponseModel(string $requestedModel) : ResponseModel {
        $class = $requestedModel;
        $instance = new $class;
        $schema = $this->schemaFactory->schema($class);
        $jsonSchema = $schema->toArray($this->functionCallFactory->onObjectRef(...));
        $functionCall = $this->functionCallFactory->render(
            $jsonSchema,
            $this->functionName,
            $this->functionDescription
        );
        // make model object
        return new ResponseModel(
            $class,
            $instance,
            $schema,
            $jsonSchema,
            $functionCall,
        );
    }

    private function makeArrayResponseModel(array $requestedModel) : ResponseModel {
        $class = $requestedModel['$comment'] ?? null;
        if (empty($class)) {
            throw new Exception('Provided JSON schema must contain $comment field with fully qualified class name');
        }
        $instance = new $class;
        $schema = $this->schemaBuilder->fromArray($requestedModel);
        $jsonSchema = $requestedModel;
        $functionCall = $this->functionCallFactory->render(
            $jsonSchema,
            $this->functionName,
            $this->functionDescription
        );
        // make model object
        return new ResponseModel(
            $class,
            $instance,
            $schema,
            $jsonSchema,
            $functionCall,
        );
    }

    private function makeSchemaProviderResponseModel(mixed $requestedModel) : ResponseModel {
        if (is_object($requestedModel)) {
            $class = get_class($requestedModel);
            $instance = $requestedModel;
        } else {
            $class = $requestedModel;
            $instance = new $class;
        }
        $jsonSchema = $instance->toJsonSchema();
        $schema = $this->schemaBuilder->fromArray($jsonSchema);
        $functionCall = $this->functionCallFactory->render(
            $jsonSchema,
            $this->functionName,
            $this->functionDescription
        );
        // make model object
        return new ResponseModel(
            $class,
            $instance,
            $schema,
            $jsonSchema,
            $functionCall,
        );
    }

    private function makeSchemaResponseModel(ObjectSchema $requestedModel) : ResponseModel {
        $schema = $requestedModel;
        $class = $schema->type->class;
        $instance = new $class;
        $schema = $requestedModel;
        $jsonSchema = $schema->toArray($this->functionCallFactory->onObjectRef(...));
        $functionCall = $this->functionCallFactory->render(
            $jsonSchema,
            $this->functionName,
            $this->functionDescription
        );
        // make model object
        return new ResponseModel(
            $class,
            $instance,
            $schema,
            $jsonSchema,
            $functionCall,
        );
    }

    private function makeInstanceResponseModel(object $requestedModel) : ResponseModel {
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $schema = $this->schemaFactory->schema($class);
        $jsonSchema = $schema->toArray($this->functionCallFactory->onObjectRef(...));
        $functionCall = $this->functionCallFactory->render(
            $jsonSchema,
            $this->functionName,
            $this->functionDescription
        );
        // make model object
        return new ResponseModel(
            $class,
            $instance,
            $schema,
            $jsonSchema,
            $functionCall,
        );
    }

    private function canReceiveEvents(mixed $requestedModel, ResponseModel $responseModel) : array {
        return match (true) {
            //$requestedModel instanceof ObjectSchema => $requestedModel->eventHandlers,
            is_subclass_of($requestedModel, CanReceiveEvents::class) => true,
            is_subclass_of($responseModel, CanReceiveEvents::class) => true,
            is_string($requestedModel) => [],
            is_array($requestedModel) => [],
            default => [],
        };
    }
}