<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Events\HttpRequestFailed;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Http\Exceptions\ConnectionException;
use Cognesy\Http\Exceptions\HttpExceptionFactory;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\TimeoutException;
use CurlHandle;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Native cURL-based HTTP driver implementation
 *
 * This driver uses PHP's built-in cURL extension to handle HTTP requests,
 * providing zero-dependency HTTP client functionality.
 */
class CurlDriver implements CanHandleHttpRequest
{
    protected HttpClientConfig $config;
    protected EventDispatcherInterface $events;

    public function __construct(
        HttpClientConfig $config,
        EventDispatcherInterface $events,
        ?object $clientInstance = null,
    ) {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('cURL extension is not loaded');
        }

        $this->config = $config;
        $this->events = $events;

        if ($clientInstance !== null) {
            throw new \InvalidArgumentException('CurlDriver does not support external client instances');
        }
    }

    #[\Override]
    public function handle(HttpRequest $request): HttpResponse {
        $startTime = microtime(true);
        $this->dispatchRequestSent($request);

        if ($request->isStreamed()) {
            return $this->handleStreaming($request, $startTime);
        }

        $curl = curl_init();

        try {
            $this->configureCurl($curl, $request);
            $responseBody = $this->executeCurl($curl, $request, $startTime);
            $statusCode = $this->getStatusCode($curl);
            $headers = $this->parseHeaders($curl);

            $this->validateStatusCodeOrFail($statusCode, $responseBody, $request, $startTime);
            $this->dispatchResponseReceived($statusCode);

            return $this->buildHttpResponse($statusCode, $headers, $responseBody, $request);
        } finally {
            curl_close($curl);
        }
    }

    // INTERNAL /////////////////////////////////////////////

    private function dispatchRequestSent(HttpRequest $request): void {
        $this->events->dispatch(new HttpRequestSent([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
        ]));
    }

    private array $responseHeaders = [];

    private function configureCurl(CurlHandle $curl, HttpRequest $request): void {
        // Set URL
        curl_setopt($curl, CURLOPT_URL, $request->url());

        // Set HTTP method
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($request->method()));

        // Set headers
        $headers = [];
        foreach ($request->headers() as $name => $value) {
            $headers[] = is_array($value)
                ? "{$name}: " . implode(', ', $value)
                : "{$name}: {$value}";
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // Set body if present
        $body = $request->body()->toString();
        if (!empty($body)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        // Return response body (non-streaming)
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // Capture response headers via callback
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function($curl, $header) {
            $length = strlen($header);
            $header = trim($header);

            if (empty($header)) {
                return $length;
            }

            // Skip HTTP status line
            if (str_starts_with($header, 'HTTP/')) {
                return $length;
            }

            // Parse header
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);

                // Headers can appear multiple times (e.g., Set-Cookie)
                if (!isset($this->responseHeaders[$name])) {
                    $this->responseHeaders[$name] = [];
                }
                $this->responseHeaders[$name][] = $value;
            }

            return $length;
        });

        // Follow redirects
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 5);

        // Set timeouts
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->config->connectTimeout ?? 3);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->config->requestTimeout ?? 30);

        // SSL verification
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

        // HTTP version - try HTTP/2 if available, fallback to HTTP/1.1
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
    }

    private function handleStreaming(HttpRequest $request, float $startTime): HttpResponse {
        $curl = curl_init();

        // Per-request containers shared with callbacks
        $this->responseHeaders = [];
        $statusCode = 0;
        $queue = new \SplQueue();

        // URL and method
        curl_setopt($curl, CURLOPT_URL, $request->url());
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($request->method()));

        // Headers
        $headers = [];
        foreach ($request->headers() as $name => $value) {
            $headers[] = is_array($value)
                ? "{$name}: " . implode(', ', $value)
                : "{$name}: {$value}";
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // Body
        $body = $request->body()->toString();
        if (!empty($body)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        // Streaming mode: do not buffer full body
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);

        // Write callback: enqueue incoming chunks
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, function($ch, $data) use ($queue) {
            if ($data !== '') {
                $queue->enqueue($data);
            }
            return strlen($data);
        });

        // Header callback: capture status and headers
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function($ch, $headerLine) use (&$statusCode) {
            $length = strlen($headerLine);
            $line = trim($headerLine);
            if ($line === '') {
                return $length;
            }
            if (str_starts_with($line, 'HTTP/')) {
                $parts = explode(' ', $line);
                if (count($parts) >= 2) {
                    $code = (int) $parts[1];
                    if ($code > 0) {
                        $statusCode = $code;
                    }
                }
                return $length;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                if (!isset($this->responseHeaders[$name])) {
                    $this->responseHeaders[$name] = [];
                }
                $this->responseHeaders[$name][] = $value;
            }
            return $length;
        });

        // Redirects
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_MAXREDIRS, 5);

        // Timeouts
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->config->connectTimeout ?? 3);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->config->requestTimeout ?? 30);

        // SSL
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

        // HTTP/2 if available
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

        // Prepare multi handle
        $multi = curl_multi_init();
        curl_multi_add_handle($multi, $curl);

        // Prime: drive until we have status or short timeout to allow tests to read statusCode()
        $active = 1;
        $startPrime = microtime(true);
        while ($active > 0 && $statusCode === 0 && (microtime(true) - $startPrime) < 0.2) {
            $status = curl_multi_exec($multi, $active);
            if ($status !== CURLM_OK) {
                break;
            }
            if ($active > 0) {
                curl_multi_select($multi, 0.05);
            }
        }

        return new CurlStreamingHttpResponse(
            curl: $curl,
            multi: $multi,
            queue: $queue,
            events: $this->events,
            request: $request,
            config: $this->config,
            headers: $this->responseHeaders,
            statusCode: $statusCode,
            streamChunkSize: $this->config->streamChunkSize ?? 256,
        );
    }

    private function executeCurl(CurlHandle $curl, HttpRequest $request, float $startTime): string {
        // Reset headers for this request
        $this->responseHeaders = [];

        $response = curl_exec($curl);

        if ($response === false) {
            $this->handleCurlError($curl, $request, $startTime);
        }

        // Response body is already separated (CURLOPT_HEADER is not set)
        // At this point, $response is guaranteed to be string (false case handled above)
        assert(is_string($response));
        return $response;
    }

    private function handleCurlError(CurlHandle $curl, HttpRequest $request, float $startTime): never {
        $duration = microtime(true) - $startTime;
        $errorCode = curl_errno($curl);
        $errorMessage = curl_error($curl);

        // Determine exception type based on error code
        $httpException = match (true) {
            in_array($errorCode, [CURLE_OPERATION_TIMEDOUT, CURLE_OPERATION_TIMEOUTED])
                => new TimeoutException($errorMessage, $request, $duration),
            in_array($errorCode, [
                CURLE_COULDNT_CONNECT,
                CURLE_COULDNT_RESOLVE_HOST,
                CURLE_COULDNT_RESOLVE_PROXY,
                CURLE_SSL_CONNECT_ERROR,
            ]) => new ConnectionException($errorMessage, $request, $duration),
            default => new NetworkException($errorMessage, $request, null, $duration),
        };

        $this->dispatchRequestFailed($httpException, $request, $duration);
        throw $httpException;
    }

    private function getStatusCode(CurlHandle $curl): int {
        return (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    }

    private function parseHeaders(CurlHandle $curl): array {
        // Return headers captured by CURLOPT_HEADERFUNCTION callback
        return $this->responseHeaders;
    }

    private function dispatchRequestFailed(HttpRequestException $exception, HttpRequest $request, float $duration): void {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toArray(),
            'errors' => $exception->getMessage(),
            'duration' => $duration,
        ]));
    }

    private function validateStatusCodeOrFail(int $statusCode, string $responseBody, HttpRequest $request, float $startTime): void {
        if (!$this->config->failOnError || $statusCode < 400) {
            return;
        }

        $duration = microtime(true) - $startTime;

        // Create a temporary response for the exception
        $tempResponse = new CurlHttpResponse(
            statusCode: $statusCode,
            headers: [],
            body: $responseBody,
            isStreamed: false,
            events: $this->events,
        );

        $httpException = HttpExceptionFactory::fromStatusCode(
            $statusCode,
            $request,
            $tempResponse,
            $duration
        );

        $this->dispatchStatusCodeFailed($statusCode, $request, $duration);
        throw $httpException;
    }

    private function dispatchStatusCodeFailed(int $statusCode, HttpRequest $request, float $duration): void {
        $this->events->dispatch(new HttpRequestFailed([
            'url' => $request->url(),
            'method' => $request->method(),
            'statusCode' => $statusCode,
            'duration' => $duration,
        ]));
    }

    private function dispatchResponseReceived(int $statusCode): void {
        $this->events->dispatch(new HttpResponseReceived([
            'statusCode' => $statusCode
        ]));
    }

    private function buildHttpResponse(int $statusCode, array $headers, string $body, HttpRequest $request): HttpResponse {
        return new CurlHttpResponse(
            statusCode: $statusCode,
            headers: $headers,
            body: $body,
            isStreamed: $request->isStreamed(),
            events: $this->events,
            streamChunkSize: $this->config->streamChunkSize,
        );
    }
}
