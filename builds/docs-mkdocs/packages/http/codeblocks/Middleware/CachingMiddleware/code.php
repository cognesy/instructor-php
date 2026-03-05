<?php declare(strict_types=1);

namespace Middleware\CachingMiddleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Psr\SimpleCache\CacheInterface;

final class CachingMiddleware implements HttpMiddleware
{
    public function __construct(
        private CacheInterface $cache,
        private int $ttl = 300,
    ) {}

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        if (strtoupper($request->method()) !== 'GET') {
            return $next->handle($request);
        }

        $cacheKey = sha1($request->method() . $request->url() . $request->body()->toString());
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached)) {
            return HttpResponse::sync(
                statusCode: (int) ($cached['status'] ?? 200),
                headers: (array) ($cached['headers'] ?? []),
                body: (string) ($cached['body'] ?? ''),
            );
        }

        $response = $next->handle($request);

        if (!$response->isStreamed() && $response->statusCode() >= 200 && $response->statusCode() < 300) {
            $this->cache->set($cacheKey, [
                'status' => $response->statusCode(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ], $this->ttl);
        }

        return $response;
    }
}
