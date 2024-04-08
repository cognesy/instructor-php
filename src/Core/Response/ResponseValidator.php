<?php

namespace Cognesy\Instructor\Core\Response;

use Cognesy\Instructor\Contracts\CanValidateObject;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Data\ValidationResult;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\ResponseHandler\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidated;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidationAttempt;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidationFailed;
use Cognesy\Instructor\Utils\Result;

class ResponseValidator
{
    public function __construct(
        private EventDispatcher $events,
        private CanValidateObject $validator,
    ) {}

    /**
     * Validate deserialized response object
     */
    public function validate(object $response) : Result {
        $validation = match(true) {
            $response instanceof CanValidateSelf => $this->validateSelf($response),
            default => $this->validateObject($response)
        };
        if ($validation->isInvalid()) {
            $this->events->dispatch(new ResponseValidationFailed($validation));
            return Result::failure($validation->getErrorMessage());
        }
        $this->events->dispatch(new ResponseValidated($validation));
        return Result::success($response);
    }

    protected function validateSelf(CanValidateSelf $response) : ValidationResult {
        $this->events->dispatch(new CustomResponseValidationAttempt($response));
        return $response->validate();
    }

    protected function validateObject(object $response) : ValidationResult {
        $this->events->dispatch(new ResponseValidationAttempt($response));
        return $this->validator->validate($response);
    }
}