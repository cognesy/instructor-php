<?php declare(strict_types=1);

namespace Cognesy\Instructor\Validation;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\Response\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidated;
use Cognesy\Instructor\Events\Response\ResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseValidator implements CanValidateResponse
{
    public function __construct(
        private EventDispatcherInterface $events,
        private CanValidateObject $validator,
        /** @phpstan-ignore-next-line */
        private StructuredOutputConfig $config,
    ) {}

    /**
     * Validate deserialized response object
     */
    #[\Override]
    public function validate(object $response, ResponseModel $responseModel) : Result {
        $validation = match(true) {
            $response instanceof CanValidateSelf => $this->validateSelf($response),
            default => $this->validateObject($response)
        };
        $this->events->dispatch(match(true) {
            $validation->isInvalid() => new ResponseValidationFailed(['validation' => $validation->toArray()]),
            default => new ResponseValidated(['validation' => $validation->toArray()])
        });
        return match(true) {
            $validation->isInvalid() => Result::failure($validation->getErrorMessage()),
            default => Result::success($response)
        };
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function validateSelf(CanValidateSelf $response) : ValidationResult {
        $this->events->dispatch(new CustomResponseValidationAttempt(['response' => json_encode($response)]));
        return $response->validate();
    }

    protected function validateObject(object $response) : ValidationResult {
        $this->events->dispatch(new ResponseValidationAttempt(['object' => $response]));
        try {
            return $this->validator->validate($response);
        } catch (\Throwable $error) {
            return ValidationResult::invalid(
                new ValidationError(field: 'exception', value: null, message: $error->getMessage()),
                'Validator threw an exception',
            );
        }
    }
}
