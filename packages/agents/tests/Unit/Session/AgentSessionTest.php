<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Session;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionStatus;
use Cognesy\Agents\Template\Data\AgentDefinition;

function makeSession(
    ?AgentSessionInfo $header = null,
    ?AgentDefinition $definition = null,
    ?AgentState $state = null,
): AgentSession {
    return new AgentSession(
        header: $header ?? AgentSessionInfo::fresh(new SessionId('s1'), 'agent', 'Agent'),
        definition: $definition ?? new AgentDefinition(name: 'agent', description: 'test', systemPrompt: 'You are a test agent.'),
        state: $state ?? AgentState::empty(),
    );
}

it('assembles immutable aggregate', function () {
    $session = makeSession();

    expect($session->sessionId())->toBe('s1');
    expect($session->status())->toBe(SessionStatus::Active);
    expect($session->version())->toBe(0);
    expect($session->definition()->name)->toBe('agent');
    expect($session->state())->toBeInstanceOf(AgentState::class);
    expect($session->info())->toBeInstanceOf(AgentSessionInfo::class);
});

it('withState updates state only, not status', function () {
    $session = makeSession();
    $newState = AgentState::empty()->withSystemPrompt('Updated prompt');
    $updated = $session->withState($newState);

    expect($updated->state()->context()->systemPrompt())->toBe('Updated prompt');
    expect($updated->status())->toBe(SessionStatus::Active);
    expect($session->state()->context()->systemPrompt())->not->toBe('Updated prompt');
});

it('domain mutators do not change version or updatedAt', function () {
    $session = makeSession();
    $updated = $session->withState(AgentState::empty());

    expect($updated->version())->toBe($session->version());
    expect($updated->info()->updatedAt()->format(\DateTimeImmutable::ATOM))
        ->toBe($session->info()->updatedAt()->format(\DateTimeImmutable::ATOM));
});

it('suspended transitions to Suspended', function () {
    $session = makeSession();
    $suspended = $session->suspended();

    expect($suspended->status())->toBe(SessionStatus::Suspended);
    expect($session->status())->toBe(SessionStatus::Active);
});

it('resumed transitions to Active', function () {
    $session = makeSession()->suspended();
    $resumed = $session->resumed();

    expect($resumed->status())->toBe(SessionStatus::Active);
});

it('completed transitions to Completed', function () {
    $session = makeSession();
    $completed = $session->completed();

    expect($completed->status())->toBe(SessionStatus::Completed);
});

it('failed transitions to Failed', function () {
    $session = makeSession();
    $failed = $session->failed();

    expect($failed->status())->toBe(SessionStatus::Failed);
});

it('deleted transitions to Deleted', function () {
    $session = makeSession();
    $deleted = $session->deleted();

    expect($deleted->status())->toBe(SessionStatus::Deleted);
});

it('withParentId updates parent', function () {
    $session = makeSession();
    $withParent = $session->withParentId(new SessionId('parent-1'));

    expect($withParent->info()->parentId())->toBe('parent-1');
    expect($session->info()->parentId())->toBeNull();
});

it('convenience accessors delegate to header', function () {
    $session = makeSession();

    expect($session->sessionId())->toBe($session->info()->sessionId());
    expect($session->status())->toBe($session->info()->status());
    expect($session->version())->toBe($session->info()->version());
});

it('round-trips through toArray/fromArray', function () {
    $session = makeSession();
    $data = $session->toArray();
    $restored = AgentSession::fromArray($data);

    expect($restored->sessionId())->toBe($session->sessionId());
    expect($restored->status())->toBe($session->status());
    expect($restored->version())->toBe($session->version());
    expect($restored->definition()->name)->toBe($session->definition()->name);
});
