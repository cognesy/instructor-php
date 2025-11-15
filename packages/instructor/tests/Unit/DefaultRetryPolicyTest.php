<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\StructuredOutputRecoveryLimitReached;
use Cognesy\Instructor\Exceptions\StructuredOutputRecoveryException;
use Cognesy\Instructor\RetryPolicy\DefaultRetryPolicy;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Utils\Result\Result;

class RetryPolicyTestModel {
    public int $value;
}


it('should allow retry when max retries not reached', function () {
    $events = new EventDispatcher();
    $policy = new DefaultRetryPolicy($events);

    $execution = (new StructuredOutputExecution())
        ->with(
            responseModel: makeAnyResponseModel(RetryPolicyTestModel::class),
            config: (new StructuredOutputConfig())->with(maxRetries: 2)
        );

    $validationResult = Result::failure('Validation failed');

    $shouldRetry = $policy->shouldRetry($execution, $validationResult);

    expect($shouldRetry)->toBeTrue();
});

it('should not allow retry when max retries reached', function () {
    $events = new EventDispatcher();
    $policy = new DefaultRetryPolicy($events);

    $execution = (new StructuredOutputExecution())
        ->with(
            responseModel: makeAnyResponseModel(RetryPolicyTestModel::class),
            config: (new StructuredOutputConfig())->with(maxRetries: 1)
        );

    // Simulate TWO failed attempts to exceed maxRetries=1
    $execution = $execution->withFailedAttempt(
        inferenceResponse: new InferenceResponse(content: 'bad'),
        errors: ['First error'],
    );
    $execution = $execution->withFailedAttempt(
        inferenceResponse: new InferenceResponse(content: 'bad'),
        errors: ['Second error'],
    );

    $validationResult = Result::failure('Validation failed again');

    $shouldRetry = $policy->shouldRetry($execution, $validationResult);

    expect($shouldRetry)->toBeFalse();
});

it('records failure and dispatches event', function () {
    $events = new EventDispatcher();
    $eventFired = false;
    $events->addListener(NewValidationRecoveryAttempt::class, function() use (&$eventFired) {
        $eventFired = true;
    });

    $policy = new DefaultRetryPolicy($events);

    $execution = (new StructuredOutputExecution())
        ->with(
            responseModel: makeAnyResponseModel(RetryPolicyTestModel::class),
            config: (new StructuredOutputConfig())->with(maxRetries: 2)
        );

    $validationResult = Result::failure('Validation failed');
    $inference = new InferenceResponse(content: '{"value": "not a number"}');
    $partial = PartialInferenceResponse::empty();

    $updated = $policy->recordFailure($execution, $validationResult, $inference, $partial);

    expect($eventFired)->toBeTrue();
    expect($updated->attemptCount())->toBe(1);
    // Note: errors() counts from both attempts list and currentAttempt, so we expect the same error twice
    expect($updated->attempts()->last()->errors())->toHaveCount(1);
});

it('prepareRetry returns execution unchanged by default', function () {
    $events = new EventDispatcher();
    $policy = new DefaultRetryPolicy($events);

    $execution = (new StructuredOutputExecution())
        ->with(
            responseModel: makeAnyResponseModel(RetryPolicyTestModel::class),
            config: (new StructuredOutputConfig())->with(maxRetries: 2)
        );

    $prepared = $policy->prepareRetry($execution);

    expect($prepared)->toBe($execution);
});

it('finalizeOrThrow returns value on success', function () {
    $events = new EventDispatcher();
    $policy = new DefaultRetryPolicy($events);

    $execution = (new StructuredOutputExecution())
        ->with(
            responseModel: makeAnyResponseModel(RetryPolicyTestModel::class),
            config: (new StructuredOutputConfig())->with(maxRetries: 2)
        );

    $testObject = new RetryPolicyTestModel();
    $testObject->value = 42;

    $validationResult = Result::success($testObject);

    $result = $policy->finalizeOrThrow($execution, $validationResult);

    expect($result)->toBe($testObject);
    expect($result->value)->toBe(42);
});

it('finalizeOrThrow throws exception on failure and dispatches event', function () {
    $events = new EventDispatcher();
    $eventFired = false;
    $events->addListener(StructuredOutputRecoveryLimitReached::class, function() use (&$eventFired) {
        $eventFired = true;
    });

    $policy = new DefaultRetryPolicy($events);

    $execution = (new StructuredOutputExecution())
        ->with(
            responseModel: makeAnyResponseModel(RetryPolicyTestModel::class),
            config: (new StructuredOutputConfig())->with(maxRetries: 1)
        );

    // Record a failed attempt
    $execution = $execution->withFailedAttempt(
        inferenceResponse: new InferenceResponse(content: 'bad'),
        errors: ['Validation failed'],
    );

    $validationResult = Result::failure('Final validation failure');

    expect(fn() => $policy->finalizeOrThrow($execution, $validationResult))
        ->toThrow(StructuredOutputRecoveryException::class);

    expect($eventFired)->toBeTrue();
});
