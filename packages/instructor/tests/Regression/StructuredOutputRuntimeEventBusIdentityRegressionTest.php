<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Events\HttpClientBuilt;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Events\InferenceDriverBuilt;

it('reuses provided event bus across structured output runtime graph', function () {
    $events = new EventDispatcher('test.structured-output.runtime.graph');
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event::class;
    });

    $runtime = StructuredOutputRuntime::fromConfig(
        config: new LLMConfig(
            apiUrl: 'https://api.openai.com/v1',
            apiKey: 'test-key',
            model: 'gpt-4o-mini',
            driver: 'openai',
        ),
        events: $events,
    );

    $runtime->create(new StructuredOutputRequest(
        messages: Messages::fromString('hello'),
        requestedSchema: ['type' => 'object'],
    ));

    expect($captured)->toContain(HttpClientBuilt::class);
    expect($captured)->toContain(InferenceDriverBuilt::class);
    expect($captured)->toContain(StructuredOutputRequestReceived::class);
});

it('allows fluent event registration on structured output runtime', function () {
    $events = new EventDispatcher('test.structured-output.runtime.listeners');
    $received = 0;
    $tapped = 0;

    $runtime = StructuredOutputRuntime::fromConfig(
        config: new LLMConfig(
            apiUrl: 'https://api.openai.com/v1',
            apiKey: 'test-key',
            model: 'gpt-4o-mini',
            driver: 'openai',
        ),
        events: $events,
    )
        ->onEvent(StructuredOutputRequestReceived::class, static function () use (&$received): void {
            $received++;
        })
        ->wiretap(static function () use (&$tapped): void {
            $tapped++;
        });

    $runtime->create(new StructuredOutputRequest(
        messages: Messages::fromString('hello'),
        requestedSchema: ['type' => 'object'],
    ));

    expect($received)->toBe(1);
    expect($tapped)->toBeGreaterThan(0);
});
