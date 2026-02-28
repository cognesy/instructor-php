<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;

it('uses the injected runtime instance', function () {
    $runtime = makeStructuredRuntime(driver: new FakeInferenceDriver());
    $structuredOutput = new StructuredOutput($runtime);

    expect($structuredOutput->runtime())->toBe($runtime);
});

it('keeps runtime stable across request-only mutations', function () {
    $runtime = StructuredOutputRuntime::fromDefaults();
    $structuredOutput = new StructuredOutput($runtime);

    $changed = $structuredOutput
        ->withMessages('hello')
        ->withResponseClass(\stdClass::class);

    expect($changed->runtime())->toBe($runtime);
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

    expect($structuredOutput->runtime())->toBe($second);
});

it('creates a facade with runtime from preset via static using()', function () {
    $structuredOutput = StructuredOutput::using('openai');

    expect($structuredOutput)->toBeInstanceOf(StructuredOutput::class);
    expect($structuredOutput->runtime())->toBeInstanceOf(StructuredOutputRuntime::class);
});
