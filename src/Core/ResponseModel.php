<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanDeserializeJson;
use Cognesy\Instructor\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Contracts\CanSelfValidate;
use Cognesy\Instructor\Contracts\CanValidateResponse;
use Cognesy\Instructor\Deserializers\Symfony\Deserializer;
use Cognesy\Instructor\Validators\Symfony\Validator;

class ResponseModel
{
    public mixed $instance; // calculated
    public ?string $class; // calculated
    public ?array $functionCall; // calculated

    public string $functionName = 'extract_data';
    public string $functionDescription = 'Extract data from provided content';
    private CanDeserializeResponse $deserializer;
    private CanValidateResponse $validator;

    public function __construct(
        string                 $class = null,
        mixed                  $instance = null,
        array                  $functionCall = null,
        CanDeserializeResponse $deserializer = null,
        CanValidateResponse    $validator = null,
    )
    {
        $this->class = $class;
        $this->instance = $instance;
        $this->functionCall = $functionCall;
        $this->deserializer = $deserializer ?? new Deserializer();
        $this->validator = $validator ?? new Validator();
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
