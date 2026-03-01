<?php declare(strict_types=1);

namespace Troubleshooting\DistributedTracing;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

final class TraceHeaderMiddleware implements HttpMiddleware
{
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        $traceId = bin2hex(random_bytes(16));
        $request = $request->withHeader('X-Trace-Id', $traceId);

        return $next->handle($request);
    }
}
