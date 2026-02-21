<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Session;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;

function makeStoreSession(string $id = 's1'): AgentSession {
    return new AgentSession(
        header: AgentSessionInfo::fresh(new SessionId($id), 'agent', 'Agent'),
        definition: new AgentDefinition(name: 'agent', description: 'test', systemPrompt: 'Test'),
        state: AgentState::empty(),
    );
}

it('save and load round-trip', function () {
    $store = new InMemorySessionStore();
    $session = makeStoreSession();

    $result = $store->save($session);
    expect($result->isOk())->toBeTrue();

    $loaded = $store->load(new SessionId('s1'));
    expect($loaded)->not->toBeNull();
    expect($loaded->sessionId())->toBe('s1');
});

it('save increments version on first save', function () {
    $store = new InMemorySessionStore();
    $session = makeStoreSession();

    $result = $store->save($session);

    expect($result->session->version())->toBe(1);
});

it('save increments version on subsequent save', function () {
    $store = new InMemorySessionStore();
    $session = makeStoreSession();

    $result1 = $store->save($session);
    $result2 = $store->save($result1->session);

    expect($result2->session->version())->toBe(2);
});

it('save returns conflict on stale version', function () {
    $store = new InMemorySessionStore();
    $session = makeStoreSession();
    $store->save($session);

    // Try saving original again (version 0, but stored is now 1)
    $result = $store->save($session);

    expect($result->isConflict())->toBeTrue();
});

it('save returns conflict for new session with non-zero version', function () {
    $store = new InMemorySessionStore();
    $info = new AgentSessionInfo(
        sessionId: new SessionId('s1'),
        parentId: null,
        status: \Cognesy\Agents\Session\SessionStatus::Active,
        version: 5,
        agentName: 'agent',
        agentLabel: 'Agent',
        createdAt: new \DateTimeImmutable(),
        updatedAt: new \DateTimeImmutable(),
    );
    $session = new AgentSession(
        header: $info,
        definition: new AgentDefinition(name: 'agent', description: 'test', systemPrompt: 'Test'),
        state: AgentState::empty(),
    );

    $result = $store->save($session);
    expect($result->isConflict())->toBeTrue();
});

it('save updates updatedAt', function () {
    $store = new InMemorySessionStore();
    $session = makeStoreSession();

    $result = $store->save($session);
    $loaded = $store->load(new SessionId('s1'));

    expect($loaded->info()->updatedAt()->getTimestamp())
        ->toBeGreaterThanOrEqual($session->info()->updatedAt()->getTimestamp());
});

it('listHeaders returns SessionInfoList of stored sessions', function () {
    $store = new InMemorySessionStore();
    $store->save(makeStoreSession('s1'));
    $store->save(makeStoreSession('s2'));

    $list = $store->listHeaders();

    expect($list)->toBeInstanceOf(SessionInfoList::class);
    expect($list->count())->toBe(2);
});

it('delete removes session', function () {
    $store = new InMemorySessionStore();
    $store->save(makeStoreSession('s1'));

    $store->delete(new SessionId('s1'));

    expect($store->exists(new SessionId('s1')))->toBeFalse();
    expect($store->load(new SessionId('s1')))->toBeNull();
});

it('exists reflects stored state', function () {
    $store = new InMemorySessionStore();

    expect($store->exists(new SessionId('s1')))->toBeFalse();

    $store->save(makeStoreSession('s1'));
    expect($store->exists(new SessionId('s1')))->toBeTrue();
});
