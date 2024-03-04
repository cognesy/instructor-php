<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\CanDeserialize;
use Cognesy\Instructor\Contracts\CanDeserializeJson;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanSelfValidate;
use Cognesy\Instructor\Deserializers\Symfony\Deserializer;
use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\Schema;
use Cognesy\Instructor\Schema\PropertyInfoBased\Factories\FunctionCallFactory;
use Cognesy\Instructor\Validators\Symfony\Validator;
use Exception;

class ResponseModel
{
    protected mixed $value;

    protected mixed $instance; // calculated
    protected ?string $class; // calculated

    public ?array $functionCall; // calculated
    public string $functionName = 'extract_data';
    public string $functionDescription = 'Extract data from provided content';
    private Deserializer $deserializer;
    private Validator $validator;

    public function __construct(mixed $value, CanDeserialize $deserializer = null, Validator $validator = null)
    {
        $this->value = $value;
        $this->deserializer = $deserializer ?? new Deserializer();
        $this->validator = $validator ?? new Validator();
        $this->functionCall = $this->makeFunctionCall($value);
    }

    /**
     * Get validation errors
     */
    public function errors() : string {
        return $this->validator->errors();
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
     * Generate function call data (depending on the response model type)
     */
    protected function makeFunctionCall(string|object|array $requestedModel) {
        if (is_string($requestedModel)) {
            $this->class = $requestedModel;
            $this->instance = null;
            return (new FunctionCallFactory)->fromClass(
                $requestedModel,
                $this->functionName,
                $this->functionDescription
            );
        }

        if (is_array($requestedModel)) {
            $this->class = $requestedModel['$comment'] ?? null;
            if (empty($this->class)) {
                throw new Exception('Provided JSON schema must contain $comment field with fully qualified class name');
            }
            $this->instance = null;
            return (new FunctionCallFactory)->fromArray(
                $requestedModel,
                $this->functionName,
                $this->functionDescription
            );
        }

        if (is_subclass_of($requestedModel, CanProvideSchema::class)) {
            $this->class = get_class($requestedModel);
            $this->instance = $requestedModel;
            return (new FunctionCallFactory)->fromArray(
                $requestedModel->toJsonSchema(),
                $this->functionName,
                $this->functionDescription
            );
        }

        if ($requestedModel instanceof Schema) {
            $this->class = $requestedModel->type->class;
            $this->instance = $requestedModel;
            return (new FunctionCallFactory)->fromSchema(
                $requestedModel,
                $this->functionName,
                $this->functionDescription
            );
        }

        $this->class = get_class($requestedModel);
        $this->instance = null;
        return (new FunctionCallFactory)->fromClass(
            get_class($requestedModel),
            $this->functionName,
            $this->functionDescription
        );
    }
}