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
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

class ResponseValidator implements CanValidateResponse
{
    public function __construct(
        private EventDispatcherInterface $events,
        /** @var CanValidateObject[]|class-string[] $validators */
        private array $validators,
        /** @phpstan-ignore-next-line */
        private StructuredOutputConfig $config,
    ) {}

    /**
     * Validate deserialized response object
     */
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

    /** @param CanValidateObject[] $validators */
    public function appendValidators(array $validators) : self {
        $this->validators = array_merge($this->validators, $validators);
        return $this;
    }

    /** @param CanValidateObject[] $validators */
    public function setValidators(array $validators) : self {
        $this->validators = $validators;
        return $this;
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function validateSelf(CanValidateSelf $response) : ValidationResult {
        $this->events->dispatch(new CustomResponseValidationAttempt(['response' => json_encode($response)]));
        return $response->validate();
    }

    protected function validateObject(object $response) : ValidationResult {
        $this->events->dispatch(new ResponseValidationAttempt(['object' => $response]));
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
