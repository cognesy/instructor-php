<?php

namespace Cognesy\Instructor\Validation;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Response\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidated;
use Cognesy\Instructor\Events\Response\ResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;
use Cognesy\Instructor\Utils\Result\Result;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Exception;

class ResponseValidator
{
    use Traits\ResponseValidator\HandlesMutation;

    public function __construct(
        private EventDispatcher $events,
        /** @var CanValidateObject[] $validators */
        private array $validators,
    ) {}

    /**
     * Validate deserialized response object
     */
    public function validate(object $response) : Result {
        $validation = match(true) {
            $response instanceof CanValidateSelf => $this->validateSelf($response),
            default => $this->validateObject($response)
        };
        $this->events->dispatch(match(true) {
            $validation->isInvalid() => new ResponseValidationFailed($validation),
            default => new ResponseValidated($validation)
        });
        return match(true) {
            $validation->isInvalid() => Result::failure($validation->getErrorMessage()),
            default => Result::success($response)
        };
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function validateSelf(CanValidateSelf $response) : ValidationResult {
        $this->events->dispatch(new CustomResponseValidationAttempt($response));
        return $response->validate();
    }

    protected function validateObject(object $response) : ValidationResult {
        $this->events->dispatch(new ResponseValidationAttempt($response));
        $results = [];
        foreach ($this->validators as $validator) {
            $validator = match(true) {
                is_string($validator) && is_subclass_of($validator, CanValidateObject::class) => new $validator(),
                $validator instanceof CanValidateObject => $validator,
                default => throw new Exception('Validator must implement CanValidateObject interface'),
            };
            // TODO: how do we handle exceptions here?
            $results[] = $validator->validate($response);
        }
        return ValidationResult::merge($results);
    }
}

//$chain = Chain::for($response)
//    ->through(
//        processors: array_map(
//            fn($v) => fn($data) => $v->validate($data),
//            array_merge($this->validators, [new SelfValidator])
//        ),
//        onNull: Chain::CONTINUE_ON_NULL
//    )
//    ->then(function($data) {
//        $this->events->dispatch(match(true) {
//            $validation->isInvalid() => new ResponseValidationFailed($validation),
//            default => new ResponseValidated($validation)
//        });
//        return Result::success($data);
//    });
//return $chain->result();
