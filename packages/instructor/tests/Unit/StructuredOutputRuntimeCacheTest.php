<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

it('reuses runtime when infrastructure remains unchanged', function () {
    $driver = new FakeInferenceRequestDriver();
    $structuredOutput = (new StructuredOutput())
        ->withLLMConfig(new LLMConfig(model: 'test-model'))
        ->withDriver($driver);

    $first = $structuredOutput->toRuntime();
    $second = $structuredOutput->toRuntime();

    expect($second)->toBe($first);
});

it('does not invalidate runtime cache on request-only mutations', function () {
    $driver = new FakeInferenceRequestDriver();
    $structuredOutput = (new StructuredOutput())
        ->withLLMConfig(new LLMConfig(model: 'test-model'))
        ->withDriver($driver);

    $first = $structuredOutput->toRuntime();
    $second = $structuredOutput->withMessages('hello')->toRuntime();

    expect($second)->toBe($first);
});

it('invalidates runtime cache on infrastructure mutations', function () {
    $driver = new FakeInferenceRequestDriver();
    $structuredOutput = (new StructuredOutput())
        ->withLLMConfig(new LLMConfig(model: 'test-model'))
        ->withDriver($driver);

    $first = $structuredOutput->toRuntime();
    $second = $structuredOutput->withOutputMode(OutputMode::Json)->toRuntime();

    expect($second)->not->toBe($first);
});

it('invalidates runtime cache when event handler is replaced', function () {
    $driver = new FakeInferenceRequestDriver();
    $structuredOutput = (new StructuredOutput())
        ->withLLMConfig(new LLMConfig(model: 'test-model'))
        ->withDriver($driver);

    $first = $structuredOutput->toRuntime();
    $second = $structuredOutput->withEventHandler(new EventDispatcher())->toRuntime();

    expect($second)->not->toBe($first);
});

it('returns a new facade instance from with mutators', function () {
    $driver = new FakeInferenceRequestDriver();
    $structuredOutput = (new StructuredOutput())
        ->withLLMConfig(new LLMConfig(model: 'test-model'))
        ->withDriver($driver);

    $derived = $structuredOutput->withMessages('hello');

    expect($derived)->not->toBe($structuredOutput);
});
