<?php declare(strict_types=1);

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Domain\Trace\TraceContext;

/**
 * End-to-end cover for distributed trace propagation.
 *
 * The middleware and the propagator had unit tests before this file, and both passed while
 * no runtime path existed: nothing registered TraceContextMiddleware and nothing wrote the
 * metadata it reads, so `traceparent` never reached a real request. These go through the
 * builder and assert on what the driver received - the only place the answer is not
 * self-fulfilling.
 */

/** Builds a client whose driver records what actually went on the wire. */
function tracedClient(): array
{
    $mock = null;
    $client = (new HttpClientBuilder())
        ->withMock(function (MockHttpDriver $driver) use (&$mock): void {
            $mock = $driver;
            $driver->addResponse(MockHttpResponseFactory::success(body: '{}'), fn() => true);
        })
        ->create();

    return [$client, $mock];
}

function tracedRequest(): HttpRequest
{
    return new HttpRequest('https://api.example.com/v1/chat', 'POST', [], '{}', []);
}

it('injects traceparent on a request stamped through the telemetry seam', function () {
    [$client, $mock] = tracedClient();
    $context = TraceContext::fresh();

    $client->send(HttpRequestTelemetry::withTraceContext(tracedRequest(), $context))->get();

    expect($mock->getLastRequest()?->headers('traceparent'))->toBe($context->traceparent());
});

it('carries tracestate alongside traceparent when the context has one', function () {
    [$client, $mock] = tracedClient();
    $context = TraceContext::fromTraceparent(
        '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        'vendor=abc',
    );

    $client->send(HttpRequestTelemetry::withTraceContext(tracedRequest(), $context))->get();

    $sent = $mock->getLastRequest();
    expect($sent?->headers('traceparent'))->toBe('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01')
        ->and($sent?->headers('tracestate'))->toBe('vendor=abc');
});

it('propagates the context of a live span so the peer joins that trace', function () {
    // The producer half of the seam: a caller stamps the context of a span it has open
    // (Telemetry::traceContext() returns exactly this), and the remote peer becomes a
    // child of that span rather than the root of an unrelated trace.
    [$client, $mock] = tracedClient();

    $span = (new TraceRegistry())->openRoot('llm.call', 'llm.call')->reference();
    $context = $span->asTraceContext();

    $client->send(HttpRequestTelemetry::withTraceContext(tracedRequest(), $context))->get();

    $traceparent = $mock->getLastRequest()?->headers('traceparent');
    expect($traceparent)->toBe($context->traceparent())
        ->and($traceparent)->toContain($span->traceId()->value());
});

it('leaves requests untouched when no trace context was stamped', function () {
    [$client, $mock] = tracedClient();

    $client->send(tracedRequest())->get();

    $sent = $mock->getLastRequest();
    expect($sent?->headers('traceparent'))->toBe([])
        ->and($sent?->headers('tracestate'))->toBe([]);
});

it('registers the propagation middleware by default', function () {
    $names = array_column(
        (new HttpClientBuilder())->createRuntime()->middlewareStack()->toDebugArray(),
        'name',
    );

    expect($names)->toContain('internal:trace-context');
});

it('keeps the stamped context on the envelope the projector consumes', function () {
    $context = TraceContext::fresh();
    $request = HttpRequestTelemetry::withTraceContext(tracedRequest(), $context);

    expect(HttpRequestTelemetry::requestEnvelope($request)->trace()?->traceparent())
        ->toBe($context->traceparent());
});
