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
            $validation->isInvalid() => new ResponseValidationFailed(['validation' => $this->validationPayload($validation)]),
            default => new ResponseValidated(['validation' => $this->validationPayload($validation)])
        });
        return match(true) {
            $validation->isInvalid() => Result::failure($validation->getErrorMessage()),
            default => Result::success($response)
        };
    }

    // INTERNAL ////////////////////////////////////////////////////////

    protected function validateSelf(CanValidateSelf $response) : ValidationResult {
        $this->events->dispatch(new CustomResponseValidationAttempt($this->objectSummary($response)));
        return $response->validate();
    }

    protected function validateObject(object $response) : ValidationResult {
        $this->events->dispatch(new ResponseValidationAttempt($this->objectSummary($response)));
        try {
            return $this->validator->validate($response);
        } catch (\Throwable $error) {
            return ValidationResult::invalid(
                new ValidationError(field: 'exception', value: null, message: $error->getMessage()),
                'Validator threw an exception',
            );
        }
    }

    private function objectSummary(object $response) : array
    {
        return [
            'responseClass' => $response::class,
            'fieldCount' => count(get_object_vars($response)),
        ];
    }

    private function validationPayload(ValidationResult $validation) : array
    {
        return [
            'isValid' => $validation->isValid(),
            'message' => $validation->message,
            'errors' => array_map(
                fn(ValidationError $error): array => [
                    'field' => $error->field,
                    'value' => $this->normalizeValue($error->value),
                    'message' => $error->message,
                ],
                $validation->errors,
            ),
        ];
    }

    private function normalizeValue(mixed $value) : mixed
    {
        return match (true) {
            is_object($value) => $value::class,
            is_array($value) => array_map($this->normalizeValue(...), $value),
            default => $value,
        };
    }
}
