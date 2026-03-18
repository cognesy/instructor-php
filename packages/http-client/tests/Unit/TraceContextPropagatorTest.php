<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Telemetry\TraceContextPropagator;
use Cognesy\Telemetry\Domain\Trace\TraceContext;

it('injects trace headers into an http request', function () {
    $propagator = new TraceContextPropagator();
    $request = new HttpRequest('https://example.com', 'GET', [], '', []);
    $context = TraceContext::fresh();

    $updated = $propagator->inject($request, $context);

    expect($updated->headers('traceparent'))->toBe($context->traceparent());
});
