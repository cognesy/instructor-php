<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Http\Middleware\MiddlewareStack;

test('HttpRequest mutators return new instances', function() {
    $request = new HttpRequest('https://api.example.com/items', 'GET', [], '', []);
    $withHeader = $request->withHeader('X-Test', '1');
    $withStreaming = $request->withStreaming(true);

    expect($withHeader)->not()->toBe($request);
    expect($withStreaming)->not()->toBe($request);
    expect($request->headers())->not()->toHaveKey('X-Test');
    expect($request->isStreamed())->toBeFalse();
    expect($withHeader->headers()['X-Test'])->toBe('1');
    expect($withStreaming->isStreamed())->toBeTrue();
});

test('MiddlewareStack mutators do not mutate previous instance', function() {
    $stack = new MiddlewareStack(new EventDispatcher());
    $middleware = new class implements HttpMiddleware {
        public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
            return $next->handle($request);
        }
    };

    $updatedStack = $stack->append($middleware, 'test');

    expect($stack->all())->toHaveCount(0);
    expect($updatedStack->all())->toHaveCount(1);
    expect($updatedStack->has('test'))->toBeTrue();
    expect($stack->has('test'))->toBeFalse();
});

test('HttpClient withMiddleware does not mutate original client', function() {
    $driver = new MockHttpDriver();
    $driver->addResponse(
        MockHttpResponseFactory::success(body: '{"ok":true}'),
        'https://api.example.com/items',
        'GET'
    );

    $client = (new HttpClientBuilder())->withDriver($driver)->create();
    $decoratedClient = $client->withMiddleware(new class implements HttpMiddleware {
        public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
            $request = $request->withHeader('X-Test', 'middleware');
            return $next->handle($request);
        }
    });

    $request = new HttpRequest('https://api.example.com/items', 'GET', [], '', []);

    $client->withRequest($request)->get();
    $plainRequest = $driver->getLastRequest();
    expect($plainRequest->headers())->not()->toHaveKey('X-Test');

    $decoratedClient->withRequest($request)->get();
    $decoratedRequest = $driver->getLastRequest();
    expect($decoratedRequest->headers())->toHaveKey('X-Test');
    expect($decoratedRequest->headers()['X-Test'])->toBe('middleware');
});
