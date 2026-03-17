<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsUsage;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsResponseReceived;
use Cognesy\Polyglot\Embeddings\PendingEmbeddings;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;

it('emits minimal array payload for streamed inference response events', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $request = new InferenceRequest(
        messages: Messages::fromString('hello'),
        model: 'gpt-test',
    );

    $stream = new InferenceStream(
        execution: InferenceExecution::fromRequest($request),
        driver: new \Cognesy\Polyglot\Tests\Support\FakeInferenceDriver(
            streamBatches: [[
                new PartialInferenceDelta(contentDelta: 'Hello', finishReason: 'stop', usage: new InferenceUsage(outputTokens: 1)),
            ]],
        ),
        eventDispatcher: $events,
    );

    foreach ($stream->deltas() as $_delta) {
    }

    $event = collectPolyglotEvent($captured, InferenceResponseCreated::class);

    expect($event->data)->toMatchArray([
        'executionId' => $stream->execution()->id->toString(),
        'requestId' => $request->id()->toString(),
        'model' => 'gpt-test',
        'finishReason' => 'stop',
        'contentLength' => 5,
        'reasoningContentLength' => 0,
        'hasToolCalls' => false,
        'toolCallCount' => 0,
        'usage' => [
            'input' => 0,
            'output' => 1,
            'cacheWrite' => 0,
            'cacheRead' => 0,
            'reasoning' => 0,
        ],
        'isPartial' => false,
    ]);

    expect($event->data['responseId'])->toBeString();
});

it('emits minimal array payload for sync inference response events', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $request = new InferenceRequest(
        messages: Messages::fromString('hello'),
        model: 'gpt-sync',
    );

    $driver = new class(
        new LLMConfig(),
        new class implements CanSendHttpRequests {
            public function send(HttpRequest $request): PendingHttpResponse
            {
                return new PendingHttpResponse($request, new class implements CanHandleHttpRequest {
                    public function handle(HttpRequest $request): HttpResponse
                    {
                        return HttpResponse::sync(
                            statusCode: 201,
                            headers: ['x-test' => '1'],
                            body: '{"ok":true}',
                        );
                    }
                });
            }
        },
        $events,
        new class implements CanTranslateInferenceRequest {
            public function toHttpRequest(InferenceRequest $request): HttpRequest
            {
                return new HttpRequest('https://example.com/inference', 'POST', [], '', []);
            }
        },
        new class implements CanTranslateInferenceResponse {
            public function fromResponse(HttpResponse $response): ?InferenceResponse
            {
                return new InferenceResponse(
                    content: 'OK',
                    finishReason: 'stop',
                    usage: new InferenceUsage(outputTokens: 2),
                    responseData: $response,
                );
            }

            public function fromStreamDeltas(iterable $eventBodies, ?HttpResponse $responseData = null): iterable
            {
                return [];
            }

            public function toEventBody(string $data): string|bool
            {
                return $data;
            }
        },
    ) extends BaseInferenceRequestDriver {};

    $driver->makeResponseFor($request);
    $event = collectPolyglotEvent($captured, InferenceResponseCreated::class);

    expect($event->data)->toMatchArray([
        'requestId' => $request->id()->toString(),
        'model' => 'gpt-sync',
        'statusCode' => 201,
        'finishReason' => 'stop',
        'contentLength' => 2,
        'reasoningContentLength' => 0,
        'hasToolCalls' => false,
        'toolCallCount' => 0,
        'usage' => [
            'input' => 0,
            'output' => 2,
            'cacheWrite' => 0,
            'cacheRead' => 0,
            'reasoning' => 0,
        ],
        'isPartial' => false,
    ]);

    expect($event->data['responseId'])->toBeString();
});

it('emits minimal array payload for embeddings response events', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $request = new EmbeddingsRequest(
        input: ['hello', 'world'],
        model: 'text-embedding-test',
    );

    $pending = new PendingEmbeddings(
        request: $request,
        driver: new class implements CanHandleVectorization {
            public function handle(EmbeddingsRequest $request): HttpResponse
            {
                return HttpResponse::sync(statusCode: 200, headers: [], body: '{"ok":true}');
            }

            public function fromData(array $data): ?EmbeddingsResponse
            {
                return new EmbeddingsResponse(
                    vectors: [
                        new Vector([0.1, 0.2, 0.3], 0),
                        new Vector([0.4, 0.5, 0.6], 1),
                    ],
                    usage: new EmbeddingsUsage(inputTokens: 12),
                );
            }
        },
        events: $events,
    );

    $pending->get();
    $event = collectPolyglotEvent($captured, EmbeddingsResponseReceived::class);

    expect($event->data)->toBe([
        'model' => 'text-embedding-test',
        'inputCount' => 2,
        'vectorCount' => 2,
        'dimensions' => 3,
        'usage' => ['input' => 12],
    ]);
});

function collectPolyglotEvent(array $events, string $class): object
{
    foreach ($events as $event) {
        if ($event instanceof $class) {
            return $event;
        }
    }

    throw new RuntimeException("Missing event: {$class}");
}
