<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

it('uses the injected runtime instance', function () {
    $runtime = makeStructuredRuntime(driver: new FakeInferenceDriver());
    $structuredOutput = new StructuredOutput($runtime);

    expect($structuredOutput)->toBeInstanceOf(StructuredOutput::class);
    expect(fn() => $structuredOutput->withResponseClass(\stdClass::class)->create())
        ->not->toThrow(Throwable::class);
});

it('keeps runtime stable across request-only mutations', function () {
    $runtime = StructuredOutputRuntime::fromDefaults();
    $structuredOutput = new StructuredOutput($runtime);

    $changed = $structuredOutput
        ->withMessages('hello')
        ->withResponseClass(\stdClass::class);

    expect($changed)->not->toBe($structuredOutput);
    expect(fn() => $changed->create())->not->toThrow(Throwable::class);
});

it('returns a new facade instance from request mutators', function () {
    $structuredOutput = new StructuredOutput();

    $derived = $structuredOutput->withMessages('hello');

    expect($derived)->not->toBe($structuredOutput);
});

it('allows runtime replacement explicitly', function () {
    $first = StructuredOutputRuntime::fromDefaults();
    $second = StructuredOutputRuntime::fromDefaults();

    $structuredOutput = (new StructuredOutput($first))->withRuntime($second);

    expect(fn() => $structuredOutput
        ->withResponseClass(\stdClass::class)
        ->create())->not->toThrow(Throwable::class);
});

it('creates a facade with runtime from driver via static fromConfig()', function () {
    $structuredOutput = StructuredOutput::fromConfig(LLMConfig::fromArray(['driver' => 'openai']));

    expect($structuredOutput)->toBeInstanceOf(StructuredOutput::class);
});
