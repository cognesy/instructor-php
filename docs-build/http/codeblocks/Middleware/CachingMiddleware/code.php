<?php

namespace Middleware\CachingMiddleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpResponse;
use Cognesy\Http\Middleware\Base\BaseMiddleware;
use Psr\SimpleCache\CacheInterface;

class CachingMiddleware extends BaseMiddleware
{
    private CacheInterface $cache;
    private int $ttl;

    public function __construct(CacheInterface $cache, int $ttl = 3600)
    {
        $this->cache = $cache;
        $this->ttl = $ttl;
    }

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next->handle($request);
        }

        // Generate a cache key for this request
        $cacheKey = $this->generateCacheKey($request);

        // Check if we have a cached response
        if ($this->cache->has($cacheKey)) {
            $cachedData = $this->cache->get($cacheKey);

            // Create a response from the cached data
            return new MockHttpResponse(
                statusCode: $cachedData['status_code'],
                headers: $cachedData['headers'],
                body: $cachedData['body']
            );
        }

        // If not in cache, make the actual request
        $response = $next->handle($request);

        // Cache the response if it was successful
        if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
            $this->cache->set(
                $cacheKey,
                [
                    'status_code' => $response->statusCode(),
                    'headers' => $response->headers(),
                    'body' => $response->body(),
                ],
                $this->ttl
            );
        }

        return $response;
    }

    private function generateCacheKey(HttpRequest $request): string
    {
        return md5($request->method() . $request->url() . $request->body()->toString());
    }
}
