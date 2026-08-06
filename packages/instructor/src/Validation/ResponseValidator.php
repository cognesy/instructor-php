<?php declare(strict_types=1);

namespace Cognesy\Instructor\Validation;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\Response\CustomResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidated;
use Cognesy\Instructor\Events\Response\ResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;
use Cognesy\Instructor\Telemetry\PhaseTelemetryContext;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\Contracts\CanValidateResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

class ResponseValidator implements CanValidateResponse
{
    public function __construct(
        private EventDispatcherInterface $events,
        private CanValidateObject $validator,
        /** @phpstan-ignore-next-line */
        private StructuredOutputConfig $config,
    ) {}

    /**
     * Validate deserialized response object.
     *
     * The attempt event opens the validation span and validated/failed closes it - that pair
     * already existed, so `$telemetry` only stamps it rather than introducing a lifecycle
     * alongside it. Every exit path emits exactly one closing event, including the throw.
     */
    #[\Override]
    public function validate(
        object $response,
        ResponseModel $responseModel,
        ?PhaseTelemetryContext $telemetry = null,
    ) : Result {
        $stamp = $telemetry?->eventData() ?? [];
        try {
            $validation = match(true) {
                $response instanceof CanValidateSelf => $this->validateSelf($response, $stamp),
                default => $this->validateObject($response, $stamp)
            };
        } catch (Throwable $error) {
            $this->events->dispatch(new ResponseValidationFailed([
                ...$stamp,
                'errorMessage' => 'Response object validation failed.',
                'errorType' => $error::class,
            ]));
            return Result::failure($error);
        }
        $this->events->dispatch(match(true) {
            $validation->isInvalid() => new ResponseValidationFailed([
                ...$stamp,
                'errorMessage' => $validation->getErrorMessage(),
                'validation' => $this->validationPayload($validation),
            ]),
            default => new ResponseValidated([
                ...$stamp,
                'validation' => $this->validationPayload($validation),
            ])
        });
        return match(true) {
            $validation->isInvalid() => Result::failure($validation->getErrorMessage()),
            default => Result::success($response)
        };
    }

    // INTERNAL ////////////////////////////////////////////////////////

    /** @param array<string, mixed> $stamp */
    protected function validateSelf(CanValidateSelf $response, array $stamp = []) : ValidationResult {
        $this->events->dispatch(new CustomResponseValidationAttempt([
            ...$stamp,
            ...$this->objectSummary($response),
            'validator' => 'self',
        ]));
        return $response->validate();
    }

    /** @param array<string, mixed> $stamp */
    protected function validateObject(object $response, array $stamp = []) : ValidationResult {
        $this->events->dispatch(new ResponseValidationAttempt([
            ...$stamp,
            ...$this->objectSummary($response),
            'validator' => $this->validator::class,
        ]));
        return $this->validator->validate($response);
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
