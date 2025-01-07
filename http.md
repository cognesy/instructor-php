Project Path: Http

Source Tree:

```
Http
├── HttpClient.php
├── Adapters
│   ├── LaravelResponse.php
│   ├── SymfonyResponse.php
│   └── PsrResponse.php
├── Drivers
│   ├── LaravelDriver.php
│   ├── SymfonyDriver.php
│   └── GuzzleDriver.php
├── Contracts
│   ├── CanHandleHttp.php
│   └── CanAccessResponse.php
├── Enums
│   └── HttpClientType.php
├── Events
└── Data
    ├── HttpClientConfig.php
    └── HttpClientRequest.php

```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/HttpClient.php`:

```php
<?php

namespace Cognesy\Instructor\Features\Http;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\Data\HttpClientConfig;
use Cognesy\Instructor\Features\Http\Drivers\GuzzleDriver;
use Cognesy\Instructor\Features\Http\Drivers\LaravelDriver;
use Cognesy\Instructor\Features\Http\Drivers\SymfonyDriver;
use Cognesy\Instructor\Features\Http\Enums\HttpClientType;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

/**
 * The HttpClient class is responsible for managing HTTP client configurations and instantiating
 * appropriate HTTP driver implementations based on the provided configuration.
 *
 * @property EventDispatcher $events  Instance for dispatching events.
 * @property CanHandleHttp $driver    Instance that handles HTTP requests.
 */
class HttpClient
{
    protected EventDispatcher $events;
    protected CanHandleHttp $driver;

    /**
     * Constructor method for initializing the HTTP client.
     *
     * @param string $client The client configuration name to load.
     * @param EventDispatcher|null $events The event dispatcher instance to use.
     * @return void
     */
    public function __construct(string $client = '', EventDispatcher $events = null) {
        $this->events = $events ?? new EventDispatcher();
        $config = HttpClientConfig::load($client ?: Settings::get('http', "defaultClient"));
        $this->driver = $this->makeDriver($config);
    }

    /**
     * Static factory method to create an instance of the HTTP handler.
     *
     * @param string $client The client configuration name to load.
     * @param EventDispatcher|null $events The event dispatcher instance to use.
     * @return CanHandleHttp Returns an instance that can handle HTTP operations.
     */
    public static function make(string $client = '', ?EventDispatcher $events = null) : CanHandleHttp {
        return (new self($client, $events))->get();
    }

    /**
     * Configures the HttpClient instance with the given client name.
     *
     * @param string $name The name of the client to load the configuration for.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withClient(string $name) : self {
        $config = HttpClientConfig::load($name);
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    /**
     * Configures the HttpClient instance with the given configuration.
     *
     * @param HttpClientConfig $config The configuration object to set up the HttpClient.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withConfig(HttpClientConfig $config) : self {
        $this->driver = $this->makeDriver($config);
        return $this;
    }

    /**
     * Sets the HTTP handler driver for the instance.
     *
     * @param CanHandleHttp $driver The driver capable of handling HTTP requests.
     * @return self Returns the instance of the class for method chaining.
     */
    public function withDriver(CanHandleHttp $driver) : self {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Retrieves the current HTTP handler instance.
     *
     * @return CanHandleHttp The HTTP handler associated with the current context.
     */
    public function get() : CanHandleHttp {
        return $this->driver;
    }

    // INTERNAL ///////////////////////////////////////////////////////

    /**
     * Creates an HTTP driver instance based on the specified configuration.
     *
     * @param HttpClientConfig $config The configuration object defining the type of HTTP client and its settings.
     * @return CanHandleHttp The instantiated HTTP driver corresponding to the specified client type.
     * @throws InvalidArgumentException If the specified client type is not supported.
     */
    private function makeDriver(HttpClientConfig $config) : CanHandleHttp {
        return match ($config->httpClientType) {
            HttpClientType::Guzzle => new GuzzleDriver(config: $config, events: $this->events),
            HttpClientType::Symfony => new SymfonyDriver(config: $config, events: $this->events),
            httpClientType::Laravel => new LaravelDriver(config: $config, events: $this->events),
            default => throw new InvalidArgumentException("Client not supported: {$config->httpClientType->value}"),
        };
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/Adapters/LaravelResponse.php`:

```php
<?php

namespace Cognesy\Instructor\Features\Http\Adapters;

use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Generator;
use Illuminate\Http\Client\Response;

class LaravelResponse implements CanAccessResponse
{
    public function __construct(
        private Response $response,
        private bool $streaming = false
    ) {}

    public function getStatusCode(): int
    {
        return $this->response->status();
    }

    public function getHeaders(): array
    {
        return $this->response->headers();
    }

    public function getContents(): string
    {
        return $this->response->body();
    }

    public function streamContents(int $chunkSize = 1): Generator
    {
        if (!$this->streaming) {
            yield $this->getContents();
            return;
        }

        $stream = $this->response->toPsrResponse()->getBody();
        while (!$stream->eof()) {
            yield $stream->read($chunkSize);
        }
    }

//    public function streamContents(int $chunkSize = 1): Generator
//    {
//        if (!$this->streaming) {
//            yield $this->getContents();
//            return;
//        }
//
//        $resource = StreamWrapper::getResource($this->response->toPsrResponse()->getBody());
//
//        while (!feof($resource)) {
//            yield fread($resource, $chunkSize);
//        }
//
//        fclose($resource);
//    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/Adapters/SymfonyResponse.php`:

```php
<?php

namespace Cognesy\Instructor\Features\Http\Adapters;

use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Generator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SymfonyResponse implements CanAccessResponse
{
    private ResponseInterface $response;
    private HttpClientInterface $client;

    public function __construct(
        HttpClientInterface $client,
        ResponseInterface $response,
        private float $connectTimeout = 1,
    ) {
        $this->client = $client;
        $this->response = $response;
    }

    public function getStatusCode(): int {
        return $this->response->getStatusCode();
    }

    public function getHeaders(): array {
        return $this->response->getHeaders();
    }

    public function getContents(): string {
        // workaround to handle connect timeout: https://github.com/symfony/symfony/pull/57811
        foreach ($this->client->stream($this->response, $this->connectTimeout) as $chunk) {
            if ($chunk->isTimeout() && !$this->response->getInfo('connect_time')) {
                $this->response->cancel();
                throw new \Exception('Connect timeout');
            }
            break;
        }
        return $this->response->getContent();
    }

    public function streamContents(int $chunkSize = 1): Generator {
        foreach ($this->client->stream($this->response, $this->connectTimeout) as $chunk) {
            yield $chunk->getContent();
        }
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/Adapters/PsrResponse.php`:

```php
<?php

namespace Cognesy\Instructor\Features\Http\Adapters;

use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class PsrResponse implements CanAccessResponse
{
    private ResponseInterface $response;
    private StreamInterface $stream;

    public function __construct(
        ResponseInterface $response,
        StreamInterface $stream,
    ) {
        $this->response = $response;
        $this->stream = $stream;
    }

    public function getStatusCode(): int {
        return $this->response->getStatusCode();
    }

    public function getHeaders(): array {
        return $this->response->getHeaders();
    }

    public function getContents(): string {
        return $this->response->getBody()->getContents();
    }

    public function streamContents(int $chunkSize = 1): Generator {
        while (!$this->stream->eof()) {
            yield $this->stream->read($chunkSize);
        }
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/Drivers/LaravelDriver.php`:

```php
<?php

namespace Cognesy\Instructor\Features\Http\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\RequestSentToLLM;
use Cognesy\Instructor\Events\HttpClient\RequestToLLMFailed;
use Cognesy\Instructor\Events\HttpClient\ResponseReceivedFromLLM;
use Cognesy\Instructor\Features\Http\Adapters\LaravelResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\Data\HttpClientConfig;
use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;
use Cognesy\Instructor\Utils\Debug\Debug;
use Exception;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;

class LaravelDriver implements CanHandleHttp
{
    private HttpFactory $factory;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?HttpFactory     $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->factory = $httpClient ?? new HttpFactory();
    }

    public function handle(HttpClientRequest $request): CanAccessResponse {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body();
        $method = $request->method();
        $streaming = $request->isStreamed();

        $this->events->dispatch(new RequestSentToLLM($url, $method, $headers, $body));
        Debug::tryDumpUrl($url);

        // Create a fresh pending request with configuration
        $pendingRequest = $this->factory
            ->timeout($this->config->requestTimeout)
            ->connectTimeout($this->config->connectTimeout)
            ->withHeaders($headers);

        if ($streaming) {
            $pendingRequest->withOptions(['stream' => true]);
        }

        try {
            // Send the request based on the method
            $response = $this->sendRequest($pendingRequest, $method, $url, $body);
        } catch (Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($url, $method, $headers, $body, $e->getMessage()));
            throw $e;
        }
        $this->events->dispatch(new ResponseReceivedFromLLM($response->status()));
        return new LaravelResponse($response, $streaming);
    }

    public function pool(array $requests, ?int $maxConcurrent = 5): array {
        $responses = $this->factory->pool(
            fn(Pool $pool) => $this->buildPoolRequests($pool, $requests, $maxConcurrent),
        );

        // Convert Laravel responses to our response type
        return array_map(
            fn(Response $response) => new LaravelResponse($response),
            $responses,
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function buildPoolRequests(Pool $pool, array $requests, int $maxConcurrent): array {
        $pool->concurrency($maxConcurrent);
        $poolRequests = [];

        foreach ($requests as $request) {
            if (!$request instanceof HttpClientRequest) {
                throw new Exception('Invalid request type in pool');
            }
            $poolRequests[] = $pool->withOptions([
                'timeout' => $this->config->requestTimeout,
                'connect_timeout' => $this->config->connectTimeout,
                'headers' => $request->headers(),
            ])->{strtolower($request->method())}(
                $request->url(),
                $request->method() === 'GET' ? [] : $request->body(),
            );
        }

        return $poolRequests;
    }

    private function sendRequest(PendingRequest $pendingRequest, string $method, string $url, array $body): Response {
        return match (strtoupper($method)) {
            'GET' => $pendingRequest->get($url),
            'POST' => $pendingRequest->post($url, $body),
            'PUT' => $pendingRequest->put($url, $body),
            'PATCH' => $pendingRequest->patch($url, $body),
            'DELETE' => $pendingRequest->delete($url, $body),
            default => throw new Exception("Unsupported HTTP method: {$method}")
        };
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/Drivers/SymfonyDriver.php`:

```php
<?php

namespace Cognesy\Instructor\Features\Http\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\RequestSentToLLM;
use Cognesy\Instructor\Events\HttpClient\RequestToLLMFailed;
use Cognesy\Instructor\Events\HttpClient\ResponseReceivedFromLLM;
use Cognesy\Instructor\Features\Http\Adapters\SymfonyResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\Data\HttpClientConfig;
use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;
use Cognesy\Instructor\Utils\Debug\Debug;
use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SymfonyDriver implements CanHandleHttp
{
    private HttpClientInterface $client;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?HttpClientInterface $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->client = $httpClient ?? HttpClient::create();
    }

    public function handle(HttpClientRequest $request) : CanAccessResponse {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body();
        $method = $request->method();
        $streaming = $request->isStreamed();

        $this->events->dispatch(new RequestSentToLLM($url, $method, $headers, $body));
        try {
            Debug::tryDumpUrl($url);
            $response = $this->client->request(
                method: $method,
                url: $url,
                options: [
                    'headers' => $headers,
                    'body' => is_array($body) ? json_encode($body) : $body,
                    'timeout' => $this->config->idleTimeout ?? 0,
                    'max_duration' => $this->config->requestTimeout ?? 30,
                    'buffer' => !$streaming,
                ]
            );
        } catch (Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($url, $method, $headers, $body, $e->getMessage()));
            throw $e;
        }
        $this->events->dispatch(new ResponseReceivedFromLLM($response->getStatusCode()));
        return new SymfonyResponse(
            client: $this->client,
            response: $response,
            connectTimeout: $this->config->connectTimeout ?? 3,
        );
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/Drivers/GuzzleDriver.php`:

```php
<?php

namespace Cognesy\Instructor\Features\Http\Drivers;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\RequestSentToLLM;
use Cognesy\Instructor\Events\HttpClient\RequestToLLMFailed;
use Cognesy\Instructor\Events\HttpClient\ResponseReceivedFromLLM;
use Cognesy\Instructor\Features\Http\Adapters\PsrResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Features\Http\Data\HttpClientConfig;
use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;
use Cognesy\Instructor\Utils\Debug\Debug;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use GuzzleHttp\Psr7\CachingStream;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleDriver implements CanHandleHttp
{
    protected Client $client;

    public function __construct(
        protected HttpClientConfig $config,
        protected ?Client $httpClient = null,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();

        // First check if debugging is enabled with a custom client
        if (Debug::isEnabled() && isset($this->httpClient)) {
            throw new InvalidArgumentException("Guzzle does not allow to inject debugging stack into existing client. Turn off debug or use default client.");
        }

        // Handle client initialization based on debug mode and custom client
        $this->client = match(true) {
            // When debugging is enabled, always create new client with debug stack
            Debug::isEnabled() => new Client(['handler' => $this->addDebugStack(HandlerStack::create())]),
            // When custom client is provided and debug is off, use it
            isset($this->httpClient) => $this->httpClient,
            // Default case: create new client without debug stack
            default => new Client()
        };
    }

    public function handle(HttpClientRequest $request) : CanAccessResponse {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body();
        $method = $request->method();
        $streaming = $request->isStreamed();

        $this->events->dispatch(new RequestSentToLLM($url, $method, $headers, $body));
        Debug::tryDumpUrl($url);
        try {
            $response = $this->client->request($method, $url, [
                'headers' => $headers,
                'json' => $body,
                'connect_timeout' => $this->config->connectTimeout ?? 3,
                'timeout' => $this->config->requestTimeout ?? 30,
                'debug' => Debug::isFlag('http.trace') ?? false,
                'stream' => $streaming,
            ]);
        } catch (Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($url, $method, $headers, $body, $e->getMessage()));
            throw $e;
        }
        $this->events->dispatch(new ResponseReceivedFromLLM($response->getStatusCode()));
        return new PsrResponse(
            response: $response,
            stream: $response->getBody()
        );
    }

    protected function addDebugStack(HandlerStack $stack) : HandlerStack {
        // add caching stream to make response body rewindable
        $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
            return $response->withBody(new CachingStream($response->getBody()));
        }));

        $stack->push(Middleware::tap(
            function (RequestInterface $request, $options) {
                Debug::tryDumpRequest($request);
                Debug::tryDumpTrace();
            },
            function ($request, $options, FulfilledPromise|RejectedPromise $response) {
                $response->then(function (ResponseInterface $response) use ($request, $options) {
                    Debug::tryDumpResponse($response, $options);
                    // need to rewind body to read it again in main flow
                    $response->getBody()->rewind();
                });
            })
        );
        return $stack;
    }
}
```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/Contracts/CanHandleHttp.php`:

```php
<?php
namespace Cognesy\Instructor\Features\Http\Contracts;

use Cognesy\Instructor\Features\Http\Data\HttpClientRequest;

interface CanHandleHttp
{
    public function handle(HttpClientRequest $request) : CanAccessResponse;
    public function pool(array $requests, ?int $maxConcurrent = null): array;
}

```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/Contracts/CanAccessResponse.php`:

```php
<?php

namespace Cognesy\Instructor\Features\Http\Contracts;

use Generator;

interface CanAccessResponse
{
    public function getStatusCode(): int;

    public function getHeaders(): array;

    /**
     * Get the response
     *
     * @return string
     */
    public function getContents(): string;

    /**
     * Read chunks of the stream
     *
     * @param int $chunkSize
     * @return Generator<string>
     */
    public function streamContents(int $chunkSize = 1): Generator;
}

```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/Enums/HttpClientType.php`:

```php
<?php

namespace Cognesy\Instructor\Features\Http\Enums;

enum HttpClientType : string
{
    case Guzzle = 'guzzle';
    case Symfony = 'symfony';
    case Laravel = 'laravel';
    case Unknown = 'unknown';
}

```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/Data/HttpClientConfig.php`:

```php
<?php

namespace Cognesy\Instructor\Features\Http\Data;

use Cognesy\Instructor\Features\Http\Enums\HttpClientType;
use Cognesy\Instructor\Utils\Settings;
use InvalidArgumentException;

class HttpClientConfig
{
    public function __construct(
        public HttpClientType $httpClientType = HttpClientType::Guzzle,
        public int $connectTimeout = 3,
        public int $requestTimeout = 30,
        public int $idleTimeout = -1,
        // Concurrency-related properties
        public int $maxConcurrent = 5,
        public int $poolTimeout = 120,
        public bool $failOnError = false,
    ) {}

    public static function load(string $client) : HttpClientConfig {
        if (!Settings::has('http', "clients.$client")) {
            throw new InvalidArgumentException("Unknown client: $client");
        }
        return new HttpClientConfig(
            httpClientType: HttpClientType::from(Settings::get('http', "clients.$client.httpClientType")),
            connectTimeout: Settings::get(group: "http", key: "clients.$client.connectTimeout", default: 30),
            requestTimeout: Settings::get("http", "clients.$client.requestTimeout", 3),
            idleTimeout: Settings::get(group: "http", key: "clients.$client.idleTimeout", default: 0),
            maxConcurrent: Settings::get("http", "clients.$client.maxConcurrent", 5),
            poolTimeout: Settings::get("http", "clients.$client.poolTimeout", 120),
            failOnError: Settings::get("http", "clients.$client.failOnError", false),
        );
    }
}

```

`/home/ddebowczyk/projects/instructor-php/src/Features/Http/Data/HttpClientRequest.php`:

```php
<?php

namespace Cognesy\Instructor\Features\Http\Data;

class HttpClientRequest
{
    public function __construct(
        public string $url,
        public string $method,
        public array $headers,
        public array $body,
        public array $options,
    ) {}

    public function url() : string {
        return $this->url;
    }

    public function method() : string {
        return $this->method;
    }

    public function headers() : array {
        return $this->headers;
    }

    public function body() : array {
        return $this->body;
    }

    public function options() : array {
        return $this->options;
    }

    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }

    public function withStreaming(bool $streaming) : self {
        $this->options['stream'] = $streaming;
        return $this;
    }
}

```