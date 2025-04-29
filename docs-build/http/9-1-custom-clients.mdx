---
title: Using Custom Clients
description: 'Learn how to create and use custom HTTP client drivers using the Instructor HTTP client API.'
---

While the Instructor HTTP client API provides built-in support for popular HTTP client libraries (Guzzle, Symfony, and Laravel), there may be cases where you need to integrate with other HTTP client libraries or create specialized implementations. This chapter covers how to create and use custom HTTP client drivers.

## Creating Custom Drivers

Creating a custom HTTP client driver involves implementing the `CanHandleHttpRequest` interface and optionally the `CanHandleRequestPool` interface for pool support.

### Implementing the CanHandleHttpRequest Interface

The `CanHandleHttpRequest` interface requires implementing a single method:

```php
interface CanHandleHttpRequest
{
    public function handle(HttpClientRequest $request): HttpClientResponse;
}
```

Here's a template for creating a custom HTTP client driver:

```php
<?php

namespace YourNamespace\Http\Drivers;

use Cognesy\Polyglot\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Data\HttpClientConfig;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Http\Events\HttpRequestFailed;
use Cognesy\Polyglot\Http\Events\HttpRequestSent;
use Cognesy\Polyglot\Http\Events\HttpResponseReceived;
use Cognesy\Polyglot\Http\Exceptions\RequestException;
use Cognesy\Utils\Events\EventDispatcher;
use Exception;

class CustomHttpDriver implements CanHandleHttpRequest
{
    /**
     * Your custom HTTP client instance
     */
    private $yourHttpClient;

    /**
     * Constructor
     */
    public function __construct(
        protected HttpClientConfig $config,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();

        // Initialize your HTTP client with the configuration
        $this->yourHttpClient = $this->createYourHttpClient();
    }

    /**
     * Handle an HTTP request
     */
    public function handle(HttpClientRequest $request): HttpClientResponse
    {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body()->toString();
        $method = $request->method();
        $streaming = $request->isStreamed();

        // Dispatch event before sending request
        $this->events->dispatch(new HttpRequestSent(
            $url,
            $method,
            $headers,
            $request->body()->toArray()
        ));

        try {
            // Use your HTTP client to make the request
            $response = $this->yourHttpClient->send($method, $url, [
                'headers' => $headers,
                'body' => $body,
                'timeout' => $this->config->requestTimeout,
                'connect_timeout' => $this->config->connectTimeout,
                'stream' => $streaming,
                // Other options relevant to your client...
            ]);

            // Dispatch event for successful response
            $this->events->dispatch(new HttpResponseReceived($response->getStatusCode()));

            // Return the response wrapped in your adapter
            return new YourHttpClientResponse($response, $streaming);

        } catch (Exception $e) {
            // Dispatch event for failed request
            $this->events->dispatch(new HttpRequestFailed(
                $url,
                $method,
                $headers,
                $request->body()->toArray(),
                $e->getMessage()
            ));

            // Wrap the exception
            throw new RequestException($e);
        }
    }

    /**
     * Create your HTTP client instance
     */
    private function createYourHttpClient()
    {
        // Initialize your HTTP client with appropriate configuration
        return new YourHttpClient([
            'connect_timeout' => $this->config->connectTimeout,
            'timeout' => $this->config->requestTimeout,
            'idle_timeout' => $this->config->idleTimeout,
            // Other options...
        ]);
    }
}
```

### Creating a Response Adapter

You also need to create a response adapter that implements the `HttpClientResponse` interface:

```php
<?php

namespace YourNamespace\Http\Adapters;

use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Generator;

class YourHttpClientResponse implements HttpClientResponse
{
    /**
     * Constructor
     */
    public function __construct(
        private $yourResponse,
        private bool $streaming = false
    ) {}

    /**
     * Get the response status code
     */
    public function statusCode(): int
    {
        return $this->yourResponse->getStatusCode();
    }

    /**
     * Get the response headers
     */
    public function headers(): array
    {
        return $this->yourResponse->getHeaders();
    }

    /**
     * Get the response body
     */
    public function body(): string
    {
        return $this->yourResponse->getBody();
    }

    /**
     * Stream the response body
     */
    public function stream(int $chunkSize = 1): Generator
    {
        if (!$this->streaming) {
            // For non-streaming responses, just yield the entire body
            yield $this->body();
            return;
        }

        // For streaming responses, yield chunks
        $stream = $this->yourResponse->getStream();

        while (!$stream->eof()) {
            yield $stream->read($chunkSize);
        }
    }
}
```

### Using Your Custom Driver

Once you've implemented your custom driver, you can use it with the `HttpClient`:

```php
use Cognesy\Polyglot\Http\HttpClient;
use Cognesy\Polyglot\Http\Data\HttpClientConfig;
use Cognesy\Polyglot\Http\Enums\HttpClientType;
use YourNamespace\Http\Drivers\CustomHttpDriver;

// Create a configuration for your custom driver
$config = new HttpClientConfig(
    httpClientType: HttpClientType::Custom->value,
    connectTimeout: 3,
    requestTimeout: 30,
    idleTimeout: -1,
    maxConcurrent: 5,
    poolTimeout: 60,
    failOnError: true
);

// Create your custom driver
$customDriver = new CustomHttpDriver($config);

// Create a client with your driver
$client = new HttpClient();
$client->withDriver($customDriver);

// Use the client as usual
$request = new HttpClientRequest(/* ... */);
$response = $client->handle($request);
```

### Real-World Example: Creating a cURL Driver

Here's a practical example of implementing a custom driver using PHP's cURL extension directly:

```php
<?php

namespace YourNamespace\Http\Drivers;

use Cognesy\Polyglot\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Data\HttpClientConfig;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Http\Events\HttpRequestFailed;
use Cognesy\Polyglot\Http\Events\HttpRequestSent;
use Cognesy\Polyglot\Http\Events\HttpResponseReceived;
use Cognesy\Polyglot\Http\Exceptions\RequestException;
use Cognesy\Utils\Events\EventDispatcher;
use YourNamespace\Http\Adapters\CurlHttpResponse;

class CurlHttpDriver implements CanHandleHttpRequest
{
    /**
     * Constructor
     */
    public function __construct(
        protected HttpClientConfig $config,
        protected ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
    }

    /**
     * Handle an HTTP request
     */
    public function handle(HttpClientRequest $request): HttpClientResponse
    {
        $url = $request->url();
        $headers = $request->headers();
        $body = $request->body()->toString();
        $method = $request->method();
        $streaming = $request->isStreamed();

        // Dispatch event before sending request
        $this->events->dispatch(new HttpRequestSent(
            $url,
            $method,
            $headers,
            $request->body()->toArray()
        ));

        try {
            // Initialize cURL
            $ch = curl_init();

            // Format headers for cURL
            $curlHeaders = [];
            foreach ($headers as $name => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $curlHeaders[] = "{$name}: {$v}";
                    }
                } else {
                    $curlHeaders[] = "{$name}: {$value}";
                }
            }

            // Set cURL options
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $curlHeaders,
                CURLOPT_CONNECTTIMEOUT => $this->config->connectTimeout,
                CURLOPT_TIMEOUT => $this->config->requestTimeout,
                CURLOPT_HEADER => true, // Include headers in output
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
            ]);

            // Set method-specific options
            switch ($method) {
                case 'POST':
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                    break;
                case 'PUT':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                    break;
                case 'PATCH':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                    break;
                case 'DELETE':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    if (!empty($body)) {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                    }
                    break;
                case 'HEAD':
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    break;
                case 'OPTIONS':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
                    break;
                case 'GET':
                default:
                    // GET is the default in cURL
                    break;
            }

            // Handle streaming if requested
            $responseBody = '';
            $responseHeaders = [];

            if ($streaming) {
                $tempHandle = null;
                $tempFile = tempnam(sys_get_temp_dir(), 'curl_stream_');
                $tempHandle = fopen($tempFile, 'w+');

                curl_setopt($ch, CURLOPT_FILE, $tempHandle);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($tempHandle) {
                    return fwrite($tempHandle, $data);
                });

                curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$responseHeaders) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) {
                        return $len;
                    }

                    $name = trim($header[0]);
                    $value = trim($header[1]);

                    $responseHeaders[$name][] = $value;
                    return $len;
                });

                $result = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                if ($result === false) {
                    throw new \RuntimeException('cURL error: ' . curl_error($ch));
                }

                // Rewind the temp file so it can be read
                rewind($tempHandle);

                // Create streaming response
                $response = new CurlHttpResponse(
                    statusCode: $statusCode,
                    headers: $responseHeaders,
                    body: '',
                    stream: $tempHandle,
                    isStreaming: true,
                    tempFile: $tempFile
                );
            } else {
                // For non-streaming responses, get the full response
                $result = curl_exec($ch);

                if ($result === false) {
                    throw new \RuntimeException('cURL error: ' . curl_error($ch));
                }

                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

                // Extract headers and body
                $headerText = substr($result, 0, $headerSize);
                $responseBody = substr($result, $headerSize);

                // Parse headers
                $headers = explode("\r\n", $headerText);
                foreach ($headers as $header) {
                    $parts = explode(':', $header, 2);
                    if (count($parts) === 2) {
                        $name = trim($parts[0]);
                        $value = trim($parts[1]);
                        $responseHeaders[$name][] = $value;
                    }
                }

                // Create regular response
                $response = new CurlHttpResponse(
                    statusCode: $statusCode,
                    headers: $responseHeaders,
                    body: $responseBody
                );
            }

            // Clean up cURL
            curl_close($ch);

            // Dispatch event for successful response
            $this->events->dispatch(new HttpResponseReceived($response->statusCode()));

            return $response;

        } catch (\Exception $e) {
            // Clean up if needed
            if (isset($ch) && is_resource($ch)) {
                curl_close($ch);
            }

            // Dispatch event for failed request
            $this->events->dispatch(new HttpRequestFailed(
                $url,
                $method,
                $headers,
                $request->body()->toArray(),
                $e->getMessage()
            ));

            // Wrap the exception
            throw new RequestException($e);
        }
    }
}
```

And here's the corresponding response adapter:

```php
<?php

namespace YourNamespace\Http\Adapters;

use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Generator;

class CurlHttpResponse implements HttpClientResponse
{
    private $stream;
    private $tempFile;
    private $isStreaming;

    /**
     * Constructor
     */
    public function __construct(
        private int $statusCode,
        private array $headers,
        private string $body,
        $stream = null,
        bool $isStreaming = false,
        ?string $tempFile = null
    ) {
        $this->stream = $stream;
        $this->isStreaming = $isStreaming;
        $this->tempFile = $tempFile;
    }

    /**
     * Destructor - clean up temp files
     */
    public function __destruct()
    {
        if ($this->stream && is_resource($this->stream)) {
            fclose($this->stream);
        }

        if ($this->tempFile && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    /**
     * Get the response status code
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the response headers
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get the response body
     */
    public function body(): string
    {
        if ($this->isStreaming && $this->stream) {
            // For streaming responses, read the entire file
            rewind($this->stream);
            $contents = stream_get_contents($this->stream);
            rewind($this->stream);
            return $contents;
        }

        return $this->body;
    }

    /**
     * Stream the response body
     */
    public function stream(int $chunkSize = 1): Generator
    {
        if ($this->isStreaming && $this->stream) {
            // For streaming responses, yield chunks from the file
            rewind($this->stream);

            while (!feof($this->stream)) {
                yield fread($this->stream, $chunkSize);
            }
        } else {
            // For non-streaming responses, yield the entire body
            yield $this->body;
        }
    }
}
```
