<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;

it('emits inference requested metadata without materializing the full request', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->addListener(InferenceRequested::class, function (InferenceRequested $event) use (&$captured): void {
        $captured[] = $event;
    });

    $request = new class(
        messages: Messages::fromString('large sensitive message history'),
        model: 'gpt-metadata',
        tools: new ToolDefinitions(new ToolDefinition(
            name: 'lookup',
            description: 'Lookup sensitive data',
            parameters: ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]],
        )),
        toolChoice: ToolChoice::auto(),
        responseFormat: ResponseFormat::jsonSchema(
            schema: ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']]],
            name: 'answer_schema',
        ),
        options: ['stream' => true, 'temperature' => 0.2, 'api_token' => 'do-not-copy'],
        cachedContext: new CachedInferenceContext(
            messages: Messages::fromString('cached sensitive message'),
            tools: new ToolDefinitions(new ToolDefinition(
                name: 'cached_lookup',
                description: 'Cached lookup',
                parameters: ['type' => 'object'],
            )),
            toolChoice: ToolChoice::required(),
            responseFormat: ResponseFormat::jsonObject(),
        ),
        responseCachePolicy: ResponseCachePolicy::Memory,
        retryPolicy: new InferenceRetryPolicy(maxAttempts: 2),
    ) extends InferenceRequest {
        public function toArray(): array {
            throw new RuntimeException('InferenceRequested must not materialize the full request.');
        }
    };

    $driver = new class(
        new LLMConfig(),
        new class implements CanSendHttpRequests {
            public function send(HttpRequest $request): PendingHttpResponse {
                return new PendingHttpResponse($request, new class implements CanHandleHttpRequest {
                    public function handle(HttpRequest $request): HttpResponse {
                        return HttpResponse::sync(
                            statusCode: 200,
                            headers: [],
                            body: '{"ok":true}',
                        );
                    }
                });
            }
        },
        $events,
        new class implements CanTranslateInferenceRequest {
            public function toHttpRequest(InferenceRequest $request): HttpRequest {
                return new HttpRequest(
                    url: 'https://example.test/inference',
                    method: 'POST',
                    headers: [],
                    body: ['stream' => $request->isStreamed()],
                    options: ['stream' => $request->isStreamed()],
                );
            }
        },
        new class implements CanTranslateInferenceResponse {
            public function fromResponse(HttpResponse $response): ?InferenceResponse {
                return new InferenceResponse(content: 'ok', finishReason: 'stop');
            }

            public function fromStreamDeltas(iterable $eventBodies, ?HttpResponse $responseData = null): iterable {
                return [];
            }

            public function toEventBody(string $data): string|bool {
                return $data;
            }
        },
    ) extends BaseInferenceRequestDriver {};

    $driver->makeResponseFor($request);

    expect($captured)->toHaveCount(1);
    $payload = $captured[0]->data;

    expect($payload)->toMatchArray([
        'requestId' => $request->id()->toString(),
        'model' => 'gpt-metadata',
        'isStreamed' => true,
        'messageCount' => 1,
        'toolCount' => 1,
        'hasTools' => true,
        'hasToolChoice' => true,
        'hasResponseFormat' => true,
        'hasOptions' => true,
        'optionKeys' => ['stream', 'temperature', 'api_token'],
        'responseCachePolicy' => 'memory',
        'hasRetryPolicy' => true,
        'hasTelemetryCorrelation' => false,
        'hasCachedContext' => true,
        'cachedMessageCount' => 1,
        'cachedToolCount' => 1,
        'hasCachedToolChoice' => true,
        'hasCachedResponseFormat' => true,
    ]);
    expect($payload)->not->toHaveKey('request');
    expect((string) json_encode($payload, JSON_THROW_ON_ERROR))
        ->not->toContain('large sensitive message history')
        ->not->toContain('cached sensitive message')
        ->not->toContain('do-not-copy')
        ->not->toContain('answer_schema')
        ->not->toContain('lookup');
});
