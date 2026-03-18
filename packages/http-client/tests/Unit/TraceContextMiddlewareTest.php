<?php declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Telemetry\TraceContextMiddleware;
use Cognesy\Telemetry\Domain\Trace\TraceContext;

final class CaptureHttpRequestHandler implements CanHandleHttpRequest
{
    public ?HttpRequest $handledRequest = null;

    #[\Override]
    public function handle(HttpRequest $request): HttpResponse
    {
        $this->handledRequest = $request;

        return HttpResponse::sync(
            statusCode: 200,
            headers: [],
            body: '',
        );
    }
}

it('injects trace context from request metadata', function () {
    $context = TraceContext::fresh();
    $request = (new HttpRequest(
        url: 'https://example.test',
        method: 'POST',
        headers: [],
        body: '',
        options: [],
    ))->withMetadataKey(TraceContextMiddleware::METADATA_KEY, $context);

    $middleware = new TraceContextMiddleware();
    $handler = new CaptureHttpRequestHandler();

    $middleware->handle($request, $handler);

    expect($handler->handledRequest)->toBeInstanceOf(HttpRequest::class);
    expect($handler->handledRequest?->headers('traceparent'))->toBe($context->traceparent());
});
