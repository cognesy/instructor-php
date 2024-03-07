<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanValidateResponse;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Factories\FunctionCallFactory;
use Exception;


class ResponseModelFactory
{
    private FunctionCallFactory $functionCallFactory;
    private CanDeserializeResponse $responseDeserializer;
    private CanValidateResponse $responseValidator;

    private string $functionName = 'extract_data';
    private string $functionDescription = 'Extract data from provided content';

    public function __construct(
        FunctionCallFactory    $functionCallFactory,
        CanDeserializeResponse $responseDeserializer,
        CanValidateResponse    $responseValidator,
    ) {
        $this->functionCallFactory = $functionCallFactory;
        $this->responseDeserializer = $responseDeserializer;
        $this->responseValidator = $responseValidator;
    }

    public function from(mixed $requestedModel) : ResponseModel {
        return match (true) {
            $requestedModel instanceof ObjectSchema => $this->makeSchemaResponseModel($requestedModel),
            is_subclass_of($requestedModel, CanProvideSchema::class) => $this->makeSchemaProviderResponseModel($requestedModel),
            is_string($requestedModel) => $this->makeStringResponseModel($requestedModel),
            is_array($requestedModel) => $this->makeArrayResponseModel($requestedModel),
            default => $this->makeInstanceResponseModel($requestedModel),
        };
    }

    private function makeStringResponseModel(string $requestedModel) : ResponseModel {
        $class = $requestedModel;
        $instance = new $class;
        $functionCall = $this->functionCallFactory->fromClass(
            $requestedModel,
            $this->functionName,
            $this->functionDescription
        );
        // make model object
        return new ResponseModel(
            $class,
            $instance,
            $functionCall,
            $this->responseDeserializer,
            $this->responseValidator
        );
    }

    private function makeArrayResponseModel(array $requestedModel) : ResponseModel {
        $class = $requestedModel['$comment'] ?? null;
        if (empty($class)) {
            throw new Exception('Provided JSON schema must contain $comment field with fully qualified class name');
        }
        $instance = new $class;
        $functionCall = $this->functionCallFactory->fromArray(
            $requestedModel,
            $this->functionName,
            $this->functionDescription
        );
        // make model object
        return new ResponseModel(
            $class,
            $instance,
            $functionCall,
            $this->responseDeserializer,
            $this->responseValidator
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
        $functionCall = $this->functionCallFactory->fromArray(
            $instance->toJsonSchema(),
            $this->functionName,
            $this->functionDescription
        );
        // make model object
        return new ResponseModel(
            $class,
            $instance,
            $functionCall,
            $this->responseDeserializer,
            $this->responseValidator
        );
    }

    private function makeSchemaResponseModel(ObjectSchema $requestedModel) : ResponseModel {
        $schema = $requestedModel;
        $class = $schema->type->class;
        $instance = new $class;
        $functionCall = $this->functionCallFactory->fromSchema(
            $schema,
            $this->functionName,
            $this->functionDescription
        );
        // make model object
        return new ResponseModel(
            $class,
            $instance,
            $functionCall,
            $this->responseDeserializer,
            $this->responseValidator
        );
    }

    private function makeInstanceResponseModel(object $requestedModel) : ResponseModel {
        $class = get_class($requestedModel);
        $instance = $requestedModel;
        $functionCall = $this->functionCallFactory->fromClass(
            get_class($requestedModel),
            $this->functionName,
            $this->functionDescription
        );
        // make model object
        return new ResponseModel(
            $class,
            $instance,
            $functionCall,
            $this->responseDeserializer,
            $this->responseValidator
        );
    }
}