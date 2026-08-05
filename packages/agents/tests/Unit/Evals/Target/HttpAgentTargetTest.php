<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Evals\Target;

use Cognesy\Agents\Evals\HttpAgentTarget;
use Cognesy\Agents\Evals\HttpTargetException;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClient;

it('creates a remote session and preserves its identity across turns', function (): void {
    $driver = new MockHttpDriver();
    $driver->addResponse(HttpResponse::sync(200, [], '{"ok":true}'), 'http://agent/health', 'GET');
    $driver->addResponse(HttpResponse::sync(201, [], '{"sessionId":"s-1"}'), 'http://agent/evals/sessions', 'POST');
    $driver->addResponse(HttpResponse::sync(200, [], '{"run":{"reply":"verify first","status":"completed","turns":1,"tools":[],"errors":""}}'), 'http://agent/evals/sessions/s-1/turns', 'POST');
    $target = new HttpAgentTarget(HttpClient::fromDriver($driver), 'http://agent', 'Bearer secret');

    $turn = $target->open()->send('refund');
    $requests = $driver->getReceivedRequests();

    expect($turn->reply())->toBe('verify first')
        ->and($requests)->toHaveCount(3)
        ->and($requests[2]->body()->toArray())->toBe(['message' => 'refund'])
        ->and($requests[2]->headers('Authorization'))->toBe('Bearer secret');
});

it('surfaces non success and malformed responses without leaking auth', function (HttpResponse $response, string $message): void {
    $driver = new MockHttpDriver();
    $driver->addResponse($response, 'http://agent/evals/sessions', 'POST');
    $target = new HttpAgentTarget(HttpClient::fromDriver($driver), 'http://agent', 'Bearer secret', healthCheck: false);

    expect(fn () => $target->open())->toThrow(HttpTargetException::class, $message);
})->with([
    'non-2xx' => [HttpResponse::sync(503, [], 'offline'), 'HTTP 503'],
    'malformed' => [HttpResponse::sync(200, [], '{bad'), 'malformed JSON'],
]);
