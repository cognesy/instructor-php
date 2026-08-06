<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Evals\Target;

use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Evals\EvalTracePolicy;
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

it('hydrates steps and the last stop signal from a remote run payload', function (): void {
    $stepPayload = [
        'id' => '5145d851-cc74-4777-8074-d2ace2d5b327',
        'turn' => 1,
        'index' => 0,
        'type' => AgentStepType::ToolExecution->value,
        'outputMessages' => [],
        'requestedToolCalls' => [],
        'toolExecutions' => [
            [
                'id' => 'd71f1b05-f01f-4d8d-8080-a46fe613f49d',
                'name' => 'lookup',
                'toolCallId' => 'call-1',
                'hasError' => false,
                'error' => null,
                'result' => 'found A1049',
                'startedAt' => '2024-01-01T00:00:00+00:00',
                'completedAt' => '2024-01-01T00:00:01+00:00',
            ],
        ],
        'finishReason' => null,
        'usage' => [],
        'startedAt' => '2024-01-01T00:00:00+00:00',
        'completedAt' => '2024-01-01T00:00:01+00:00',
        'duration' => 1.0,
        'stopSignal' => null,
        'errors' => [],
        'hasErrors' => false,
    ];
    $runPayload = [
        'reply' => 'Verified A1049',
        'status' => 'completed',
        'tools' => [],
        'turns' => 1,
        'errors' => '',
        'steps' => [$stepPayload],
        'stopSignal' => ['reason' => 'stop_requested', 'message' => 'remote stop', 'context' => [], 'source' => null],
    ];

    $driver = new MockHttpDriver();
    $driver->addResponse(HttpResponse::sync(200, [], '{"ok":true}'), 'http://agent/health', 'GET');
    $driver->addResponse(HttpResponse::sync(201, [], '{"sessionId":"s-1"}'), 'http://agent/evals/sessions', 'POST');
    $driver->addResponse(HttpResponse::sync(200, [], json_encode(['run' => $runPayload], JSON_THROW_ON_ERROR)), 'http://agent/evals/sessions/s-1/turns', 'POST');
    $target = new HttpAgentTarget(HttpClient::fromDriver($driver), 'http://agent', 'Bearer secret');

    $run = $target->open()->send('verify')->run();

    expect($run->stepCount())->toBe(1)
        ->and($run->steps()->last()?->type())->toBe(AgentStepType::ToolExecution)
        ->and($run->steps()->last()?->toolExecutions()->all()[0]->name())->toBe('lookup')
        ->and($run->stopSignal()?->message)->toBe('remote stop');
});

/**
 * Builds a minimal remote run payload wrapping a single tool-execution step,
 * with the given tool-call arguments and result substituted verbatim into the
 * payload - callers control whether those values are plain (as a third-party
 * remote target unaware of EvalTracePolicy would send them) or pre-digested
 * (as a remote target that already digested them would send them).
 *
 * @return array<string, mixed>
 */
function remoteRunPayload(mixed $arguments, mixed $result): array {
    return [
        'reply' => 'done',
        'status' => 'completed',
        'tools' => [],
        'turns' => 1,
        'errors' => '',
        'steps' => [[
            'id' => '92bdae56-aedc-455e-9104-9256c4af9831',
            'turn' => 1,
            'index' => 0,
            'type' => AgentStepType::ToolExecution->value,
            'outputMessages' => [],
            'requestedToolCalls' => [],
            'toolExecutions' => [[
                'id' => '14952f73-329b-4f67-99ab-596f8ac1fa5f',
                'name' => 'lookup',
                'toolCallId' => 'call-1',
                'arguments' => $arguments,
                'hasError' => false,
                'error' => null,
                'result' => $result,
                'startedAt' => '2024-01-01T00:00:00+00:00',
                'completedAt' => '2024-01-01T00:00:01+00:00',
            ]],
            'finishReason' => null,
            'usage' => [],
            'startedAt' => '2024-01-01T00:00:00+00:00',
            'completedAt' => '2024-01-01T00:00:01+00:00',
            'duration' => 1.0,
            'stopSignal' => null,
            'errors' => [],
            'hasErrors' => false,
        ]],
        'stopSignal' => null,
    ];
}

it('digests a remote target\'s verbatim tool payload under the default policy, so a secret it sent never reaches the serialized trace', function (): void {
    // Padded past the 120-byte preview window, exactly like the local-run SECRET
    // TEST in AgentRunTest - a short secret would legitimately show up inside a
    // bounded preview, so this proves the digest boundary, not just its presence.
    $cardSecret = str_repeat('A', 200) . 'SECRET-4111111111111111';
    $ssnSecret = str_repeat('B', 200) . 'SECRET-SSN-123-45-6789';
    $runPayload = remoteRunPayload(['card' => $cardSecret], $ssnSecret);

    $driver = new MockHttpDriver();
    $driver->addResponse(HttpResponse::sync(200, [], '{"ok":true}'), 'http://agent/health', 'GET');
    $driver->addResponse(HttpResponse::sync(201, [], '{"sessionId":"s-1"}'), 'http://agent/evals/sessions', 'POST');
    $driver->addResponse(HttpResponse::sync(200, [], json_encode(['run' => $runPayload], JSON_THROW_ON_ERROR)), 'http://agent/evals/sessions/s-1/turns', 'POST');
    $target = new HttpAgentTarget(HttpClient::fromDriver($driver), 'http://agent', 'Bearer secret');

    $run = $target->open()->send('verify')->run();
    $serialized = json_encode($run->toArray(), JSON_THROW_ON_ERROR);
    $toolExecution = $run->toArray()['steps'][0]['toolExecutions'][0];

    expect($serialized)->not->toContain('SECRET-4111111111111111')
        ->and($serialized)->not->toContain('SECRET-SSN-123-45-6789')
        ->and($toolExecution['arguments'])->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($toolExecution['result'])->toHaveKeys(['hash', 'bytes', 'preview'])
        ->and($run->steps()->last()?->toolExecutions()->all()[0]->args())->toBe(['card' => $cardSecret]);
});

it('passes an already-digested remote tool payload through unchanged on re-serialization', function (): void {
    $argumentDigest = [
        'hash' => 'sha256:eeed0884e001b29968b8aaafb5df5b7a62d20169acde3325d7cf204812e1098c',
        'bytes' => 14,
        'preview' => '{"id":"A1049"}',
    ];
    $resultDigest = [
        'hash' => 'sha256:cca20afd098f7d7d93bae2191aa3f7d3d5fdc10050928bd2aab4f9b8ce67e252',
        'bytes' => 13,
        'preview' => '"found A1049"',
    ];
    $runPayload = remoteRunPayload($argumentDigest, $resultDigest);

    $driver = new MockHttpDriver();
    $driver->addResponse(HttpResponse::sync(200, [], '{"ok":true}'), 'http://agent/health', 'GET');
    $driver->addResponse(HttpResponse::sync(201, [], '{"sessionId":"s-1"}'), 'http://agent/evals/sessions', 'POST');
    $driver->addResponse(HttpResponse::sync(200, [], json_encode(['run' => $runPayload], JSON_THROW_ON_ERROR)), 'http://agent/evals/sessions/s-1/turns', 'POST');
    $target = new HttpAgentTarget(HttpClient::fromDriver($driver), 'http://agent', 'Bearer secret');

    $run = $target->open()->send('verify')->run();
    $toolExecution = $run->toArray()['steps'][0]['toolExecutions'][0];

    expect($toolExecution['arguments'])->toBe($argumentDigest)
        ->and($toolExecution['result'])->toBe($resultDigest);
});

it('keeps a remote payload verbatim only when the HttpAgentTarget is explicitly constructed with full()', function (): void {
    $runPayload = remoteRunPayload(['id' => 'A1049'], 'found A1049');

    $driver = new MockHttpDriver();
    $driver->addResponse(HttpResponse::sync(200, [], '{"ok":true}'), 'http://agent/health', 'GET');
    $driver->addResponse(HttpResponse::sync(201, [], '{"sessionId":"s-1"}'), 'http://agent/evals/sessions', 'POST');
    $driver->addResponse(HttpResponse::sync(200, [], json_encode(['run' => $runPayload], JSON_THROW_ON_ERROR)), 'http://agent/evals/sessions/s-1/turns', 'POST');
    $target = new HttpAgentTarget(HttpClient::fromDriver($driver), 'http://agent', 'Bearer secret', policy: EvalTracePolicy::full());

    $run = $target->open()->send('verify')->run();
    $toolExecution = $run->toArray()['steps'][0]['toolExecutions'][0];

    expect($toolExecution['arguments'])->toBe(['id' => 'A1049'])
        ->and($toolExecution['result'])->toBe('found A1049');
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
