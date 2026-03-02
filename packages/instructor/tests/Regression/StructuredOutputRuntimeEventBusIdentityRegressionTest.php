<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Events\HttpClientBuilt;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;
use Cognesy\Instructor\StructuredOutputRuntime;
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
        messages: 'hello',
        requestedSchema: ['type' => 'object'],
    ));

    expect($captured)->toContain(HttpClientBuilt::class);
    expect($captured)->toContain(InferenceDriverBuilt::class);
    expect($captured)->toContain(StructuredOutputRequestReceived::class);
});
