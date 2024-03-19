<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanValidateObject;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\ResponseHandler\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidationAttempt;
use Cognesy\Instructor\Utils\Result;

class ResponseValidator
{
    public function __construct(
        private EventDispatcher $eventDispatcher,
        private CanValidateObject $validator,
    ) {}

    /**
     * Validate deserialized response object
     */
    public function validate(object $response) : Result {
        return match(true) {
            $response instanceof CanValidateSelf => $this->validateSelf($response),
            default => $this->validateObject($response)
        };
    }

    protected function validateSelf(CanValidateSelf $response) : Result {
        $this->eventDispatcher->dispatch(new CustomResponseValidationAttempt($response));
        $errors = $response->validate();
        return match(count($errors)) {
            0 => Result::success($response),
            default => Result::failure($errors)
        };
    }

    protected function validateObject(object $response) : Result {
        $this->eventDispatcher->dispatch(new ResponseValidationAttempt($response));
        $errors = $this->validator->validate($response);
        return match(count($errors)) {
            0 => Result::success($response),
            default => Result::failure($errors)
        };
    }
}