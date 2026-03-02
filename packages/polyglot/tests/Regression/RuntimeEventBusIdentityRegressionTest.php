<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Events\HttpClientBuilt;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsDriverBuilt;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Events\InferenceDriverBuilt;
use Cognesy\Polyglot\Inference\InferenceRuntime;

it('reuses provided event bus across inference runtime graph', function () {
    $events = new EventDispatcher('test.inference.runtime.graph');
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event::class;
    });

    InferenceRuntime::fromConfig(
        config: new LLMConfig(
            apiUrl: 'https://api.openai.com/v1',
            apiKey: 'test-key',
            model: 'gpt-4o-mini',
            driver: 'openai',
        ),
        events: $events,
    );

    expect($captured)->toContain(HttpClientBuilt::class);
    expect($captured)->toContain(InferenceDriverBuilt::class);
});

it('reuses provided event bus across embeddings runtime graph', function () {
    $events = new EventDispatcher('test.embeddings.runtime.graph');
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event::class;
    });

    EmbeddingsRuntime::fromConfig(
        config: new EmbeddingsConfig(
            apiUrl: 'https://api.openai.com/v1',
            apiKey: 'test-key',
            model: 'text-embedding-3-small',
            driver: 'openai',
        ),
        events: $events,
    );

    expect($captured)->toContain(HttpClientBuilt::class);
    expect($captured)->toContain(EmbeddingsDriverBuilt::class);
});
