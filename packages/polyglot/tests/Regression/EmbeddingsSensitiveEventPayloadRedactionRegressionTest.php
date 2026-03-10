<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedRequestAdapter;
use Cognesy\Polyglot\Embeddings\Contracts\EmbedResponseAdapter;
use Cognesy\Polyglot\Embeddings\Creation\EmbeddingsDriverRegistry;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Drivers\BaseEmbedDriver;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsDriverBuilt;
use Cognesy\Polyglot\Embeddings\Events\EmbeddingsFailed;
use Cognesy\Polyglot\Tests\Support\FakeEmbeddingsDriver;

it('redacts sensitive config values in EmbeddingsDriverBuilt event payload', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        if (!$event instanceof EmbeddingsDriverBuilt) {
            return;
        }

        $captured[] = $event;
    });

    $drivers = EmbeddingsDriverRegistry::make()->withDriver(
        'custom-redaction-test',
        fn($config, $httpClient, $eventBus) => new FakeEmbeddingsDriver(),
    );

    EmbeddingsRuntime::fromConfig(
        config: new EmbeddingsConfig(
            apiUrl: 'https://api.example.com',
            apiKey: 'super-secret-embed-key',
            endpoint: '/embeddings',
            model: 'text-embedding-3-small',
            metadata: ['access_token' => 'internal-embed-token'],
            driver: 'custom-redaction-test',
        ),
        events: $events,
        drivers: $drivers,
    );

    expect($captured)->toHaveCount(1);
    $payload = $captured[0]->data;

    expect($payload['config']['apiKey'])->toBe('[REDACTED]')
        ->and($payload['config']['metadata']['access_token'])->toBe('[REDACTED]')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('super-secret-embed-key')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('internal-embed-token');
});

it('redacts request headers and body in EmbeddingsFailed event payload', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        if (!$event instanceof EmbeddingsFailed) {
            return;
        }

        $captured[] = $event;
    });

    $httpClient = HttpClient::fromDriver(new class implements CanHandleHttpRequest {
        #[\Override]
        public function handle(HttpRequest $request): HttpResponse {
            throw new HttpRequestException('Embeddings request failed for ' . $request->url(), $request);
        }
    });

    $requestAdapter = new class implements EmbedRequestAdapter {
        #[\Override]
        public function toHttpClientRequest(EmbeddingsRequest $request): HttpRequest {
            return new HttpRequest(
                url: 'https://embed.example.com/v1/embeddings?api_key=url-secret-key&token=url-secret-token&q=hello',
                method: 'POST',
                headers: [
                    'Authorization' => 'Bearer top-secret-token',
                    'X-Api-Key' => 'secondary-secret',
                ],
                body: ['input' => 'sensitive embeddings payload'],
                options: ['access_token' => 'request-option-token'],
            );
        }
    };

    $responseAdapter = new class implements EmbedResponseAdapter {
        #[\Override]
        public function fromResponse(array $data): EmbeddingsResponse {
            return new EmbeddingsResponse();
        }
    };

    $driver = new class(
        new EmbeddingsConfig(apiUrl: 'https://embed.example.com', apiKey: 'driver-secret', endpoint: '/embeddings', model: 'text-embedding-3-small', driver: 'custom'),
        $httpClient,
        $events,
        $requestAdapter,
        $responseAdapter,
    ) extends BaseEmbedDriver {};

    expect(fn() => $driver->handle(new EmbeddingsRequest(input: ['hello'])))
        ->toThrow(Exception::class);

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
        ->and($payload['exception'])->not->toContain('url-secret-key')
        ->and($payload['exception'])->not->toContain('url-secret-token')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('top-secret-token')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('sensitive embeddings payload')
        ->and((string) json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('request-option-token');
});
