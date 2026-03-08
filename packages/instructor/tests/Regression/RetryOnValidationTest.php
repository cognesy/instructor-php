<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\StructuredOutputRecoveryLimitReached;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Instructor\Enums\OutputMode;
use Tests\Addons\Support\FakeInferenceDriver;

// Simple models for validation
class SyncRetryUser { public int $age; }
class StreamRetryUser { public int $age; }

class StreamValidatedUser {
    use ValidationMixin;

    public int $age;

    public function __construct(int $age = 0) {
        $this->age = $age;
    }

    public function validate(): ValidationResult {
        return match (true) {
            $this->age >= 18 => ValidationResult::valid(),
            default => ValidationResult::fieldError(
                field: 'age',
                value: $this->age,
                message: 'Age must be at least 18',
            ),
        };
    }
}

it('retries sync request after validation failure and succeeds on second attempt', function () {
    $driver = new FakeInferenceDriver(
        responses: [
            new InferenceResponse(content: 'not json'),
            new InferenceResponse(content: '{"age":21}'),
        ],
    );

    $events = new EventDispatcher();
    $attempts = 0; $limits = 0;
    $events->addListener(NewValidationRecoveryAttempt::class, function() use (&$attempts){ $attempts++; });
    $events->addListener(StructuredOutputRecoveryLimitReached::class, function() use (&$limits){ $limits++; });

    $config = (new StructuredOutputConfigBuilder())
        ->withOutputMode(OutputMode::Json)
        ->withMaxRetries(1)
        ->create();
    $runtime = makeStructuredRuntime(
        driver: $driver,
        events: $events,
        config: $config,
    );

    $pending = (new StructuredOutput($runtime))
        ->withMessages('test')
        ->withResponseClass(SyncRetryUser::class)
        ->create();

    $result = $pending->getObject();

    // Object parsed from second attempt
    expect($result)->toBeInstanceOf(SyncRetryUser::class);
    expect($result->age)->toBe(21);
    // One retry event and no limit reached
    expect($attempts)->toBe(1);
    expect($limits)->toBe(0);
    // Execution recorded two attempts
    expect($pending->execution()->attemptCount())->toBe(2);
    // Driver used twice
    expect($driver->responseCalls)->toBe(2);
});

it('retries streaming (transducer) request after validation failure and succeeds on second attempt', function () {
    // First attempt: no deltas -> empty content -> processing failure
    // Second attempt: deltas assemble into valid JSON
    $batch1 = [];
    $batch2 = [
        new PartialInferenceResponse(contentDelta: '{"age":'),
        new PartialInferenceResponse(contentDelta: '36}'),
    ];

    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [ $batch1, $batch2 ],
    );

    $events = new EventDispatcher();
    $attempts = 0; $limits = 0;
    $events->addListener(NewValidationRecoveryAttempt::class, function() use (&$attempts){ $attempts++; });
    $events->addListener(StructuredOutputRecoveryLimitReached::class, function() use (&$limits){ $limits++; });

    $config = (new StructuredOutputConfigBuilder())
        ->withOutputMode(OutputMode::Json)
        ->withMaxRetries(1)
        ->create();
    $runtime = makeStructuredRuntime(
        driver: $driver,
        events: $events,
        config: $config,
    );

    $pending = (new StructuredOutput($runtime))
        ->withMessages('test')
        ->withResponseClass(StreamRetryUser::class)
        ->create();

    // Use the streaming path
    $value = $pending->stream()->finalValue();

    expect($value)->toBeInstanceOf(StreamRetryUser::class);
    expect($value->age)->toBe(36);
    // One retry attempt event, no limit reached
    expect($attempts)->toBe(1);
    expect($limits)->toBe(0);
    // For streaming, driver calls indicate attempts (execution attempt count not exposed via stream)
    expect($driver->streamCalls)->toBe(2);
});

it('retries streaming when driver emits pre-valued object that fails validation', function () {
    $batch1 = [
        (new PartialInferenceResponse(contentDelta: ''))->withValue(new StreamValidatedUser(12)),
    ];
    $batch2 = [
        (new PartialInferenceResponse(contentDelta: ''))->withValue(new StreamValidatedUser(21)),
    ];

    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [$batch1, $batch2],
    );

    $events = new EventDispatcher();
    $attempts = 0;
    $limits = 0;
    $events->addListener(NewValidationRecoveryAttempt::class, function () use (&$attempts) {
        $attempts++;
    });
    $events->addListener(StructuredOutputRecoveryLimitReached::class, function () use (&$limits) {
        $limits++;
    });

    $config = (new StructuredOutputConfigBuilder())
        ->withOutputMode(OutputMode::Json)
        ->withMaxRetries(1)
        ->create();

    $runtime = makeStructuredRuntime(
        driver: $driver,
        events: $events,
        config: $config,
    );

    $pending = (new StructuredOutput($runtime))
        ->withMessages('test')
        ->withResponseClass(StreamValidatedUser::class)
        ->create();

    $value = $pending->stream()->finalValue();

    expect($value)->toBeInstanceOf(StreamValidatedUser::class);
    expect($value->age)->toBe(21);
    expect($attempts)->toBe(1);
    expect($limits)->toBe(0);
    expect($driver->streamCalls)->toBe(2);
});
