<?php
namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\CanDeserialize;
use Cognesy\Instructor\Contracts\CanDeserializeJson;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanSelfValidate;
use Cognesy\Instructor\Deserializers\Symfony\Deserializer;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Factories\FunctionCallFactory;
use Cognesy\Instructor\Validators\Symfony\Validator;
use Exception;

class ResponseModel
{
    public mixed $instance; // calculated
    public ?string $class; // calculated
    public ?array $functionCall; // calculated

    public string $functionName = 'extract_data';
    public string $functionDescription = 'Extract data from provided content';
    private Deserializer $deserializer;
    private Validator $validator;

    public function __construct(mixed $responseModel, CanDeserialize $deserializer = null, Validator $validator = null)
    {
        $this->deserializer = $deserializer ?? new Deserializer();
        $this->validator = $validator ?? new Validator();
        $this->functionCall = $this->makeFunctionCall($responseModel);
    }

    /**
     * Generate function call data (depending on the response model type)
     */
    protected function makeFunctionCall(string|object|array $requestedModel) : array {
        return match (true) {
            is_array($requestedModel) => $this->handleArrayResponseModel($requestedModel),
            $requestedModel instanceof ObjectSchema => $this->handleSchemaResponseModel($requestedModel),
            is_subclass_of($requestedModel, CanProvideSchema::class) => $this->handleSchemaProviderResponseModel($requestedModel),
            is_string($requestedModel) => $this->handleStringResponseModel($requestedModel),
            default => $this->handleInstanceResponseModel($requestedModel),
        };
    }

    private function handleStringResponseModel(string $requestedModel) : array {
        $this->class = $requestedModel;
        $this->instance = new $this->class;
        return (new FunctionCallFactory)->fromClass(
            $requestedModel,
            $this->functionName,
            $this->functionDescription
        );
    }

    private function handleArrayResponseModel(array $requestedModel) : array {
        $this->class = $requestedModel['$comment'] ?? null;
        if (empty($this->class)) {
            throw new Exception('Provided JSON schema must contain $comment field with fully qualified class name');
        }
        $this->instance = new $this->class;
        return (new FunctionCallFactory)->fromArray(
            $requestedModel,
            $this->functionName,
            $this->functionDescription
        );
    }

    private function handleSchemaProviderResponseModel(mixed $requestedModel) : array {
        if (is_object($requestedModel)) {
            $this->class = get_class($requestedModel);
            $this->instance = $requestedModel;
        } else {
            $this->class = $requestedModel;
            $this->instance = new $this->class;
        }
        return (new FunctionCallFactory)->fromArray(
            $this->instance->toJsonSchema(),
            $this->functionName,
            $this->functionDescription
        );
    }

    private function handleSchemaResponseModel(ObjectSchema $requestedModel) : array {
        $schema = $requestedModel;
        $this->class = $schema->type->class;
        $this->instance = new $this->class;
        return (new FunctionCallFactory)->fromSchema(
           $schema,
           $this->functionName,
           $this->functionDescription
        );
    }

    private function handleInstanceResponseModel(object $requestedModel) : array {
        $this->class = get_class($requestedModel);
        $this->instance = $requestedModel;
        return (new FunctionCallFactory)->fromClass(
           get_class($requestedModel),
           $this->functionName,
           $this->functionDescription
        );
    }

    /**
     * Deserialize JSON and validate response object
     */
    public function toResponse(string $json) {
        $object = $this->deserialize($json);
        if ($this->validate($object)) {
            return [$object, null];
        }
        return [null, $this->errors()];
    }

    /**
     * Deserialize response JSON
     */
    protected function deserialize(string $json) : mixed {
        if ($this->instance instanceof CanDeserializeJson) {
            return $this->instance->fromJson($json);
        }
        // else - use standard deserializer
        return $this->deserializer->deserialize($json, $this->class);
    }

    /**
     * Validate deserialized response object
     */
    protected function validate(object $response) : bool {
        if ($response instanceof CanSelfValidate) {
            return $response->validate();
        }
        // else - use standard validator
        return $this->validator->validate($response);
    }

    /**
     * Get validation errors
     */
    public function errors() : string {
        return $this->validator->errors();
    }
}