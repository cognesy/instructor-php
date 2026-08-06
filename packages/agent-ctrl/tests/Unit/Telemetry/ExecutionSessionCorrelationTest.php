<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Builder\AbstractBridgeBuilder;
use Cognesy\AgentCtrl\Builder\ClaudeCodeBridgeBuilder;
use Cognesy\AgentCtrl\Builder\CodexBridgeBuilder;
use Cognesy\AgentCtrl\Builder\GeminiBridgeBuilder;
use Cognesy\AgentCtrl\Builder\OpenCodeBridgeBuilder;
use Cognesy\AgentCtrl\Builder\PiBridgeBuilder;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Contract\StreamHandler;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

/**
 * Pins where session correlation enters an agent-ctrl trace.
 *
 * Before this, `agent_ctrl.execute` roots were born without a session and
 * `ResponseParsingCompleted` was the first event that carried one - so a resumed run looked
 * unrelated to its own session until parsing finished. The rule now: the root carries the
 * session iff the caller named one, and never a placeholder.
 */

/** Returns a canned response without touching a CLI. */
final class FakeSessionBridge implements AgentBridge
{
    public function __construct(private ?string $reportedSessionId) {}

    public function execute(string|Stringable $prompt): AgentResponse
    {
        return new AgentResponse(
            agentType: AgentType::ClaudeCode,
            text: 'ok',
            exitCode: 0,
            executionId: 'exec-from-bridge',
            sessionId: $this->reportedSessionId,
        );
    }

    public function executeStreaming(string|Stringable $prompt, ?StreamHandler $handler): AgentResponse
    {
        return $this->execute($prompt);
    }
}

/** The five shipped builders are final, so the base-class flow is exercised through this. */
final class FakeSessionBuilder extends AbstractBridgeBuilder
{
    private ?string $resumed = null;

    public function __construct(private ?string $reportedSessionId = null)
    {
        parent::__construct();
    }

    public function resumeSession(string $sessionId): static
    {
        $this->resumed = $sessionId;
        return $this;
    }

    #[\Override]
    public function agentType(): AgentType
    {
        return AgentType::ClaudeCode;
    }

    #[\Override]
    protected function resumedSessionId(): ?string
    {
        return $this->resumed;
    }

    #[\Override]
    public function build(): AgentBridge
    {
        return new FakeSessionBridge($this->reportedSessionId);
    }
}

/** @return list<object> */
function captureExecution(FakeSessionBuilder $builder): array
{
    $captured = [];
    $events = new EventDispatcher('test');
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });
    $builder->withEventHandler($events)->execute('hello');

    return $captured;
}

function firstOf(array $events, string $class): ?object
{
    foreach ($events as $event) {
        if ($event instanceof $class) {
            return $event;
        }
    }
    return null;
}

/** Reads the session correlation off the envelope the projector will consume. */
function envelopeSession(object $event): ?string
{
    return TelemetryEnvelope::fromArray($event->data[TelemetryEnvelope::KEY])
        ->correlation()
        ->sessionId();
}

it('births the execution root with the session the caller resumed', function () {
    $events = captureExecution((new FakeSessionBuilder())->resumeSession('sess-42'));

    $started = firstOf($events, AgentExecutionStarted::class);

    expect($started?->sessionId()?->toString())->toBe('sess-42')
        ->and(envelopeSession($started))->toBe('sess-42');
});

it('leaves the root session-less when no session was named', function () {
    $events = captureExecution(new FakeSessionBuilder());

    $started = firstOf($events, AgentExecutionStarted::class);

    // The acceptance rule: no placeholder is invented just to fill the field.
    expect($started?->sessionId())->toBeNull()
        ->and(envelopeSession($started))->toBeNull();
});

it('keeps runs that share a session as distinct execution roots', function () {
    $first = firstOf(captureExecution((new FakeSessionBuilder())->resumeSession('sess-42')), AgentExecutionStarted::class);
    $second = firstOf(captureExecution((new FakeSessionBuilder())->resumeSession('sess-42')), AgentExecutionStarted::class);

    $rootOf = fn(object $e): string => TelemetryEnvelope::fromArray($e->data[TelemetryEnvelope::KEY])
        ->correlation()
        ->rootOperationId();

    expect(envelopeSession($first))->toBe(envelopeSession($second))
        ->and($rootOf($first))->not->toBe($rootOf($second))
        ->and((string) $first->executionId())->not->toBe((string) $second->executionId());
});

it('closes the span with the session the agent reported even when start had none', function () {
    $events = captureExecution(new FakeSessionBuilder(reportedSessionId: 'sess-issued-at-runtime'));

    $started = firstOf($events, AgentExecutionStarted::class);
    $completed = firstOf($events, AgentExecutionCompleted::class);

    expect($started?->sessionId())->toBeNull()
        ->and($completed?->sessionId()?->toString())->toBe('sess-issued-at-runtime')
        ->and(envelopeSession($completed))->toBe('sess-issued-at-runtime');
});

/** Calls the protected override on a final builder. */
function resumedIdOf(object $builder): ?string
{
    $method = new ReflectionMethod($builder, 'resumedSessionId');
    return $method->invoke($builder);
}

dataset('builders naming a session', [
    'claude-code' => [fn() => (new ClaudeCodeBridgeBuilder())->resumeSession('sess-1')],
    'codex' => [fn() => (new CodexBridgeBuilder())->resumeSession('sess-1')],
    'opencode' => [fn() => (new OpenCodeBridgeBuilder())->resumeSession('sess-1')],
    'pi' => [fn() => (new PiBridgeBuilder())->resumeSession('sess-1')],
    'gemini' => [fn() => (new GeminiBridgeBuilder())->resumeSession('sess-1')],
]);

dataset('builders selecting without naming', [
    'claude-code' => [fn() => (new ClaudeCodeBridgeBuilder())->continueSession()],
    'codex' => [fn() => (new CodexBridgeBuilder())->continueSession()],
    'opencode' => [fn() => (new OpenCodeBridgeBuilder())->continueSession()],
    'pi' => [fn() => (new PiBridgeBuilder())->continueSession()],
    'gemini' => [fn() => (new GeminiBridgeBuilder())->continueSession()],
]);

it('reports the resumed session id from every shipped builder', function (Closure $make) {
    expect(resumedIdOf($make()))->toBe('sess-1');
})->with('builders naming a session');

it('reports nothing for continueSession, which selects without naming', function (Closure $make) {
    // Gemini is the sharp edge here: continueSession() writes the literal 'latest' into the
    // same field resumeSession() uses, and 'latest' is a selector, not a session id.
    expect(resumedIdOf($make()))->toBeNull();
})->with('builders selecting without naming');
