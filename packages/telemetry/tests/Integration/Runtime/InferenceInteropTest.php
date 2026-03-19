<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Telemetry\HttpClientTelemetryProjector;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Tests\Integration\Support\InteropEnv;
use Cognesy\Telemetry\Tests\Integration\Support\InteropRun;
use Cognesy\Telemetry\Tests\Integration\Support\InteropTelemetryFactory;
use Cognesy\Telemetry\Tests\Integration\Support\LangfuseQueryClient;
use Cognesy\Telemetry\Tests\Integration\Support\LogfireQueryClient;
use Cognesy\Telemetry\Tests\Integration\Support\Polling;

it('exports inference runtime telemetry to logfire and can query it back', function () {
    InteropEnv::requireLogfire();
    InteropEnv::requireOpenAi();

    $run = InteropRun::fresh('inference-logfire');
    $serviceName = $run->serviceName('tests.telemetry.inference.logfire');
    $events = new EventDispatcher($serviceName);
    $hub = InteropTelemetryFactory::logfire($serviceName);
    $client = LogfireQueryClient::fromEnv();

    (new RuntimeEventBridge(new CompositeTelemetryProjector([
        new PolyglotTelemetryProjector($hub),
        new HttpClientTelemetryProjector($hub),
    ])))->attachTo($events);

    $runtime = InferenceRuntime::fromProvider(
        provider: LLMProvider::using('openai'),
        events: $events,
    );
    $response = Inference::fromRuntime($runtime)
        ->with(
            messages: Messages::fromString($run->marker('Interop inference logfire. Reply with exactly 2 short bullets.')),
            options: ['max_tokens' => 120],
        )
        ->response();
    $hub->flush();

    $timestamp = Polling::eventually(
        probe: static fn(): ?string => $client->latestTimestampForService($serviceName),
    );

    expect($response->content())->not->toBeEmpty()
        ->and($timestamp)->not->toBeNull();
})->group('integration', 'interop', 'runtime', 'logfire');

it('exports streaming inference telemetry to logfire and can query it back', function () {
    InteropEnv::requireLogfire();
    InteropEnv::requireOpenAi();

    $run = InteropRun::fresh('stream-logfire');
    $serviceName = $run->serviceName('tests.telemetry.streaming.logfire');
    $events = new EventDispatcher($serviceName);
    $hub = InteropTelemetryFactory::logfire($serviceName);
    $client = LogfireQueryClient::fromEnv();

    (new RuntimeEventBridge(new CompositeTelemetryProjector([
        new PolyglotTelemetryProjector($hub),
        new HttpClientTelemetryProjector($hub, captureStreamingChunks: true),
    ])))->attachTo($events);

    $runtime = InferenceRuntime::fromProvider(
        provider: LLMProvider::using('openai'),
        events: $events,
    );
    $stream = Inference::fromRuntime($runtime)
        ->with(
            messages: Messages::fromString($run->marker('Interop streaming logfire. Reply with exactly 2 short bullets.')),
            options: ['max_tokens' => 140],
        )
        ->withStreaming()
        ->stream();

    $content = '';
    foreach ($stream->deltas() as $delta) {
        $content .= $delta->contentDelta;
    }
    $hub->flush();

    $timestamp = Polling::eventually(
        probe: static fn(): ?string => $client->latestTimestampForService($serviceName),
    );

    expect($content)->not->toBeEmpty()
        ->and($timestamp)->not->toBeNull();
})->group('integration', 'interop', 'runtime', 'logfire', 'streaming');

it('exports inference runtime telemetry to langfuse and can query it back', function () {
    InteropEnv::requireLangfuse();
    InteropEnv::requireOpenAi();

    $run = InteropRun::fresh('inference-langfuse');
    $prompt = $run->marker('Interop inference langfuse. Reply with exactly 2 short bullets.');
    $events = new EventDispatcher('tests.telemetry.inference.langfuse');
    $hub = InteropTelemetryFactory::langfuse();
    $client = LangfuseQueryClient::fromEnv();

    (new RuntimeEventBridge(new CompositeTelemetryProjector([
        new PolyglotTelemetryProjector($hub),
        new HttpClientTelemetryProjector($hub),
    ])))->attachTo($events);

    $runtime = InferenceRuntime::fromProvider(
        provider: LLMProvider::using('openai'),
        events: $events,
    );
    $response = Inference::fromRuntime($runtime)
        ->with(
            messages: Messages::fromString($prompt),
            options: ['max_tokens' => 120],
        )
        ->response();
    $hub->flush();

    $trace = Polling::eventually(
        probe: static fn(): ?array => $client->latestTraceMatching($prompt),
        timeoutSeconds: 60,
    );

    expect($response->content())->not->toBeEmpty()
        ->and($trace)->not->toBeNull();
})->group('integration', 'interop', 'runtime', 'langfuse');

it('exports streaming inference telemetry to langfuse and can query it back', function () {
    InteropEnv::requireLangfuse();
    InteropEnv::requireOpenAi();

    $run = InteropRun::fresh('stream-langfuse');
    $prompt = $run->marker('Interop streaming langfuse. Reply with exactly 2 short bullets.');
    $events = new EventDispatcher('tests.telemetry.streaming.langfuse');
    $hub = InteropTelemetryFactory::langfuse();
    $client = LangfuseQueryClient::fromEnv();

    (new RuntimeEventBridge(new CompositeTelemetryProjector([
        new PolyglotTelemetryProjector($hub),
        new HttpClientTelemetryProjector($hub, captureStreamingChunks: true),
    ])))->attachTo($events);

    $runtime = InferenceRuntime::fromProvider(
        provider: LLMProvider::using('openai'),
        events: $events,
    );
    $stream = Inference::fromRuntime($runtime)
        ->with(
            messages: Messages::fromString($prompt),
            options: ['max_tokens' => 140],
        )
        ->withStreaming()
        ->stream();

    $content = '';
    foreach ($stream->deltas() as $delta) {
        $content .= $delta->contentDelta;
    }
    $hub->flush();

    $trace = Polling::eventually(
        probe: static fn(): ?array => $client->latestTraceMatching($prompt),
        timeoutSeconds: 60,
    );

    expect($content)->not->toBeEmpty()
        ->and($trace)->not->toBeNull();
})->group('integration', 'interop', 'runtime', 'langfuse', 'streaming');
