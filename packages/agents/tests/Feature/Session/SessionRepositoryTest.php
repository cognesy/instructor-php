<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Session;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\Exceptions\SessionConflictException;
use Cognesy\Agents\Session\Exceptions\SessionNotFoundException;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;

function makeRepo(): SessionRepository {
    return new SessionRepository(new InMemorySessionStore());
}

function makeTestSession(string $id = 's1'): AgentSession {
    return new AgentSession(
        header: AgentSessionInfo::fresh(new SessionId($id), 'agent', 'Agent'),
        definition: new AgentDefinition(name: 'agent', description: 'test', systemPrompt: 'Test'),
        state: AgentState::empty(),
    );
}

it('creates and loads a session', function () {
    $repo = makeRepo();
    $session = makeTestSession();

    $created = $repo->create($session);
    expect($created->version())->toBe(1);

    $loaded = $repo->load(new SessionId($session->sessionId()));
    expect($loaded->sessionId())->toBe($session->sessionId());
    expect($loaded->version())->toBe(1);
});

it('saves existing session and returns persisted instance', function () {
    $repo = makeRepo();
    $created = $repo->create(makeTestSession());

    $saved = $repo->save($created);
    expect($saved->version())->toBe(2);
});

it('throws SessionNotFoundException on load miss', function () {
    $repo = makeRepo();
    $repo->load(new SessionId('nonexistent'));
})->throws(SessionNotFoundException::class);

it('throws SessionNotFoundException on save miss', function () {
    $repo = makeRepo();
    $repo->save(makeTestSession('missing'));
})->throws(SessionNotFoundException::class);

it('throws SessionConflictException on stale save', function () {
    $repo = makeRepo();
    $session = makeTestSession('s1');
    $repo->create($session);
    $repo->save($session);
})->throws(SessionConflictException::class);

it('exists returns correct value', function () {
    $repo = makeRepo();
    $session = makeTestSession();

    expect($repo->exists(new SessionId('s1')))->toBeFalse();

    $repo->create($session);
    expect($repo->exists(new SessionId('s1')))->toBeTrue();
});

it('delete removes session', function () {
    $repo = makeRepo();
    $repo->create(makeTestSession('s1'));

    $repo->delete(new SessionId('s1'));

    expect($repo->exists(new SessionId('s1')))->toBeFalse();
});

it('listHeaders returns SessionInfoList', function () {
    $repo = makeRepo();
    $repo->create(makeTestSession('s1'));
    $repo->create(makeTestSession('s2'));

    $list = $repo->listHeaders();

    expect($list)->toBeInstanceOf(SessionInfoList::class);
    expect($list->count())->toBe(2);
});
