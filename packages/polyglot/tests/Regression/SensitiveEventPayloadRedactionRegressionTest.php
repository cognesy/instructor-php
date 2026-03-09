<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverRegistry;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Events\InferenceDriverBuilt;
use Cognesy\Polyglot\Inference\Events\InferenceFailed;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('redacts sensitive config values in InferenceDriverBuilt event payload', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        if (!$event instanceof InferenceDriverBuilt) {
            return;
        }
        $captured[] = $event;
    });

    $config = new LLMConfig(
        apiUrl: 'https://api.example.com',
        apiKey: 'super-secret-api-key',
        endpoint: '/v1/chat/completions',
        model: 'test-model',
        driver: 'custom-redaction-test',
        options: ['access_token' => 'internal-token'],
    );
    $drivers = InferenceDriverRegistry::make()->withDriver(
        'custom-redaction-test',
        fn($driverConfig, $httpClient, $eventBus) => new FakeInferenceDriver(),
    );

    InferenceRuntime::fromConfig(
        config: $config,
        events: $events,
        httpClient: (new HttpClientBuilder())->create(),
        drivers: $drivers,
    );

    expect($captured)->toHaveCount(1);
    $payload = $captured[0]->data;

    expect($payload['config']['apiKey'])->toBe('[REDACTED]')
        ->and($payload['config']['options']['access_token'])->toBe('[REDACTED]')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('super-secret-api-key')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('internal-token');
});

it('redacts request headers and body in InferenceFailed event payload', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        if (!$event instanceof InferenceFailed) {
            return;
        }
        $captured[] = $event;
    });

    $httpClient = HttpClient::fromDriver(new class implements CanHandleHttpRequest {
        #[\Override]
        public function handle(HttpRequest $request): HttpResponse {
            throw new HttpRequestException('Network down', $request);
        }
    });

    $requestTranslator = new class implements CanTranslateInferenceRequest {
        #[\Override]
        public function toHttpRequest(InferenceRequest $request): HttpRequest {
            return new HttpRequest(
                url: 'https://api.example.com/v1/chat/completions?api_key=url-secret-key&token=url-secret-token&q=hello',
                method: 'POST',
                headers: [
                    'Authorization' => 'Bearer top-secret-token',
                    'X-Api-Key' => 'secondary-secret',
                ],
                body: ['prompt' => 'sensitive prompt content'],
                options: ['access_token' => 'request-option-token'],
            );
        }
    };

    $responseTranslator = new class implements CanTranslateInferenceResponse {
        #[\Override]
        public function fromResponse(HttpResponse $response): ?InferenceResponse {
            return null;
        }

        #[\Override]
        public function fromStreamDeltas(iterable $eventBodies, ?HttpResponse $responseData = null): iterable {
            return [];
        }

        #[\Override]
        public function toEventBody(string $data): string|bool {
            return $data;
        }
    };

    $driver = new class(
        new LLMConfig(apiUrl: 'https://api.example.com', apiKey: 'driver-secret', endpoint: '/v1/chat/completions', model: 'test-model', driver: 'custom'),
        $httpClient,
        $events,
        $requestTranslator,
        $responseTranslator,
    ) extends BaseInferenceRequestDriver {};

    expect(fn() => $driver->makeResponseFor(new InferenceRequest(messages: 'hello')))
        ->toThrow(\Exception::class);

    expect($captured)->toHaveCount(1);
    $payload = $captured[0]->data;
    $requestPayload = $payload['request'];

    expect($requestPayload['headers']['Authorization'])->toBe('[REDACTED]')
        ->and($requestPayload['headers']['X-Api-Key'])->toBe('[REDACTED]')
        ->and($requestPayload['body'])->toBe('[REDACTED]')
        ->and($requestPayload['options']['access_token'])->toBe('[REDACTED]')
        ->and($requestPayload['url'])->toContain('q=hello')
        ->and($requestPayload['url'])->toContain('api_key=%5BREDACTED%5D')
        ->and($requestPayload['url'])->toContain('token=%5BREDACTED%5D')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('top-secret-token')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('sensitive prompt content')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('request-option-token')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('url-secret-key')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('url-secret-token');
});
