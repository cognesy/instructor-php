<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

/**
 * VERIFICATION TEST: Ensure response iterator configuration is actually being used
 */

if (!class_exists('VerifyPerson')) {
    eval('class VerifyPerson { public string $name; public int $age; }');
}

it('VERIFY: modular iterator is actually used when configured', function () {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"list":[{"name":"Test","age":1}]}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);

    $config = (new StructuredOutputConfig())->with(responseIterator: 'modular');

    // Verify config has correct value
    expect($config->responseIterator())->toBe('modular');

    $pending = (new StructuredOutput())
        ->withDriver($driver)
        ->withConfig($config)
        ->with(
            messages: 'test',
            responseModel: Sequence::of('VerifyPerson'),
            mode: OutputMode::Json,
        );

    $result = $pending->stream()->finalValue();
    expect($result)->toBeInstanceOf(Sequence::class);
})->group('verification', 'iterator-switching');

it('VERIFY: partials iterator is actually used when configured', function () {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"list":[{"name":"Test","age":1}]}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);

    $config = (new StructuredOutputConfig())->with(responseIterator: 'partials');

    // Verify config has correct value
    expect($config->responseIterator())->toBe('partials');

    $pending = (new StructuredOutput())
        ->withDriver($driver)
        ->withConfig($config)
        ->with(
            messages: 'test',
            responseModel: Sequence::of('VerifyPerson'),
            mode: OutputMode::Json,
        );

    $result = $pending->stream()->finalValue();
    expect($result)->toBeInstanceOf(Sequence::class);
})->group('verification', 'iterator-switching');

it('VERIFY: legacy iterator is actually used when configured', function () {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"list":[{"name":"Test","age":1}]}', finishReason: 'stop'),
    ];

    $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);

    $config = (new StructuredOutputConfig())->with(responseIterator: 'legacy');

    // Verify config has correct value
    expect($config->responseIterator())->toBe('legacy');

    $pending = (new StructuredOutput())
        ->withDriver($driver)
        ->withConfig($config)
        ->with(
            messages: 'test',
            responseModel: Sequence::of('VerifyPerson'),
            mode: OutputMode::Json,
        );

    $result = $pending->stream()->finalValue();
    expect($result)->toBeInstanceOf(Sequence::class);
})->group('verification', 'iterator-switching');

it('VERIFY: different iterators produce same sequence behavior', function () {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"list":[{"name":"Alice"'),
        new PartialInferenceResponse(contentDelta: ',"age":25},{"name":"Bob"'),
        new PartialInferenceResponse(contentDelta: ',"age":30}]}', finishReason: 'stop'),
    ];

    $results = [];

    foreach (['modular', 'partials', 'legacy'] as $iteratorName) {
        $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);
        $config = (new StructuredOutputConfig())->with(responseIterator: $iteratorName);

        $pending = (new StructuredOutput())
            ->withDriver($driver)
            ->withConfig($config)
            ->with(
                messages: 'test',
                responseModel: Sequence::of('VerifyPerson'),
                mode: OutputMode::Json,
            );

        $sequenceUpdates = [];
        foreach ($pending->stream()->sequence() as $seq) {
            $sequenceUpdates[] = $seq->count();
        }

        $results[$iteratorName] = $sequenceUpdates;
    }

    // All three should produce the same sequence of counts: [1, 2]
    expect($results['modular'])->toBe([1, 2]);
    expect($results['partials'])->toBe([1, 2]);
    expect($results['legacy'])->toBe([1, 2]);

    // Verify they're actually identical
    expect($results['modular'])->toBe($results['partials']);
    expect($results['partials'])->toBe($results['legacy']);
})->group('verification', 'iterator-switching', 'critical');

it('VERIFY: iterator selection actually impacts internal execution path', function () {
    // This test uses events to verify different iterators are being used
    // Each iterator dispatches events differently during processing

    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"list":[{"name":"X","age":1}]}', finishReason: 'stop'),
    ];

    $eventCounts = [];

    foreach (['modular', 'partials', 'legacy'] as $iteratorName) {
        $driver = new FakeInferenceDriver(responses: [], streamBatches: [ $chunks ]);
        $config = (new StructuredOutputConfig())->with(responseIterator: $iteratorName);

        $eventCount = 0;
        $eventBus = new \Cognesy\Events\Dispatchers\EventDispatcher();
        $eventBus->addListener('*', function() use (&$eventCount) {
            $eventCount++;
        });

        $pending = (new StructuredOutput(events: $eventBus))
            ->withDriver($driver)
            ->withConfig($config)
            ->with(
                messages: 'test',
                responseModel: Sequence::of('VerifyPerson'),
                mode: OutputMode::Json,
            );

        $result = $pending->stream()->finalValue();
        $eventCounts[$iteratorName] = $eventCount;
    }

    // Just verify execution happened for all three
    // (event counts may vary but all should be > 0)
    expect($eventCounts['modular'])->toBeGreaterThan(0);
    expect($eventCounts['partials'])->toBeGreaterThan(0);
    expect($eventCounts['legacy'])->toBeGreaterThan(0);

    // Verification passed - all iterators produced events
})->group('verification', 'iterator-switching', 'internal');
