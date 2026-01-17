<?php declare(strict_types=1);

namespace Troubleshooting\ErrorHandling;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\HttpClient;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use YourNamespace\HttpResponse;

class ApiService
{
    private HttpClient $client;
    private LoggerInterface $logger;
    private CacheInterface $cache;
    private array $circuitBreakers = [];

    public function __construct(
        HttpClient $client,
        LoggerInterface $logger,
        CacheInterface $cache
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    public function fetchData(string $endpoint, array $params = []): array {
        $url = "https://api.example.com/{$endpoint}";

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $request = new HttpRequest(
            url: $url,
            method: 'GET',
            headers: [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getApiToken(),
            ],
            body: [],
            options: []
        );

        // Get or create a circuit breaker for this host
        $host = parse_url($url, PHP_URL_HOST);
        if (!isset($this->circuitBreakers[$host])) {
            $this->circuitBreakers[$host] = [
                'state' => 'CLOSED',
                'failures' => 0,
                'lastFailure' => 0,
                'threshold' => 5,
                'timeout' => 60,
            ];
        }

        $circuitBreaker = &$this->circuitBreakers[$host];

        // Check circuit state
        if ($circuitBreaker['state'] === 'OPEN') {
            if (time() - $circuitBreaker['lastFailure'] > $circuitBreaker['timeout']) {
                // Try again after timeout
                $circuitBreaker['state'] = 'HALF_OPEN';
                $this->logger->info("Circuit for {$host} is now half-open");
            } else {
                $this->logger->warning("Circuit for {$host} is open, using fallback");
                return $this->getFallbackData($endpoint, $params);
            }
        }

        // Try to get from cache first if it's a GET request
        $cacheKey = 'api_' . md5($url);
        $cachedData = $this->cache->get($cacheKey);

        if ($cachedData) {
            $this->logger->info("Cache hit for {$url}");
            return $cachedData;
        }

        // Attempt the request with retries
        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            try {
                $this->logger->info("Attempting request to {$url}", [
                    'attempt' => $attempts + 1,
                    'max_attempts' => $maxAttempts,
                ]);

                $response = $this->client->withRequest($request)->get();

                // If we got here in HALF_OPEN state, reset the circuit
                if ($circuitBreaker['state'] === 'HALF_OPEN') {
                    $circuitBreaker['state'] = 'CLOSED';
                    $circuitBreaker['failures'] = 0;
                    $this->logger->info("Circuit for {$host} is now closed");
                }

                // Process the response
                if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                    $data = json_decode($response->body(), true);

                    // Cache successful GET responses
                    if ($request->method() === 'GET') {
                        $this->cache->set($cacheKey, $data, 300); // 5 minutes
                    }

                    return $data;
                } else {
                    // Handle error responses
                    $error = json_decode($response->body(), true)['error'] ?? 'Unknown error';
                    $this->logger->error("API error: {$error}", [
                        'status_code' => $response->statusCode(),
                        'url' => $url,
                    ]);

                    // Record failure for circuit breaker
                    $this->recordFailure($circuitBreaker);

                    if ($response->statusCode() >= 500) {
                        // Retry server errors
                        $attempts++;
                        if ($attempts < $maxAttempts) {
                            $sleepTime = 2 ** $attempts;
                            $this->logger->info("Will retry in {$sleepTime} seconds");
                            sleep($sleepTime);
                            continue;
                        }
                    }

                    // Client errors or max retries reached
                    return $this->handleErrorResponse($response, $endpoint, $params);
                }
            } catch (HttpRequestException $e) {
                $this->logger->error("Request exception: {$e->getMessage()}", [
                    'url' => $url,
                    'attempt' => $attempts + 1,
                ]);

                // Record failure for circuit breaker
                $this->recordFailure($circuitBreaker);

                // Retry on connection errors
                $attempts++;
                if ($attempts < $maxAttempts) {
                    $sleepTime = 2 ** $attempts;
                    $this->logger->info("Will retry in {$sleepTime} seconds");
                    sleep($sleepTime);
                } else {
                    // Max retries reached, use fallback
                    return $this->getFallbackData($endpoint, $params);
                }
            }
        }

        // Should never reach here, but just in case
        return $this->getFallbackData($endpoint, $params);
    }

    private function recordFailure(array &$circuitBreaker): void {
        $circuitBreaker['failures']++;
        $circuitBreaker['lastFailure'] = time();

        if ($circuitBreaker['failures'] >= $circuitBreaker['threshold'] ||
            $circuitBreaker['state'] === 'HALF_OPEN') {
            $circuitBreaker['state'] = 'OPEN';
            $this->logger->warning("Circuit is now open due to {$circuitBreaker['failures']} failures");
        }
    }

    private function getFallbackData(string $endpoint, array $params): array {
        $this->logger->info("Using fallback data for {$endpoint}");

        // Try to get stale cached data
        $url = "https://api.example.com/{$endpoint}";
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $cacheKey = 'api_' . md5($url);
        $cachedData = $this->cache->get($cacheKey . '_stale');

        if ($cachedData) {
            return array_merge($cachedData, ['_is_stale' => true]);
        }

        // Provide minimal fallback data
        return [
            'success' => false,
            'error' => 'Service unavailable',
            '_is_fallback' => true,
        ];
    }

    private function handleErrorResponse(HttpResponse $response, string $endpoint, array $params): array {
        $statusCode = $response->statusCode();
        $body = json_decode($response->body(), true);

        // Different handling based on status code
        switch ($statusCode) {
            case 400:
                return [
                    'success' => false,
                    'error' => 'Bad request',
                    'details' => $body['error'] ?? 'Invalid request parameters',
                ];

            case 401:
            case 403:
                // Authentication/authorization error
                $this->refreshToken(); // Try to refresh token for next request
                return [
                    'success' => false,
                    'error' => 'Authentication failed',
                    'details' => $body['error'] ?? 'Please log in again',
                ];

            case 404:
                return [
                    'success' => false,
                    'error' => 'Not found',
                    'details' => "The requested {$endpoint} could not be found",
                ];

            case 429:
                // Rate limit exceeded
                $retryAfter = $response->headers()['Retry-After'][0] ?? 60;
                return [
                    'success' => false,
                    'error' => 'Rate limit exceeded',
                    'retry_after' => $retryAfter,
                ];

            default:
                // Use fallback for other errors
                return $this->getFallbackData($endpoint, $params);
        }
    }

    private function getApiToken(): string {
        // Implementation to get or refresh API token
        return 'your-api-token';
    }

    private function refreshToken(): void {
        // Implementation to refresh the token
    }
}
