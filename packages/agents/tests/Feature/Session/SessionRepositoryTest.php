<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Session;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionInfoList;
use Cognesy\Agents\Session\Exceptions\SessionNotFoundException;
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

it('saves and loads a session', function () {
    $repo = makeRepo();
    $session = makeTestSession();

    $result = $repo->save($session);
    expect($result->isOk())->toBeTrue();

    $loaded = $repo->load(new SessionId($session->sessionId()));
    expect($loaded->sessionId())->toBe($session->sessionId());
    expect($loaded->version())->toBe(1);
});

it('throws SessionNotFoundException on miss', function () {
    $repo = makeRepo();

    $repo->load(new SessionId('nonexistent'));
})->throws(SessionNotFoundException::class);

it('save returns SaveResult with persisted session', function () {
    $repo = makeRepo();
    $session = makeTestSession();

    $result = $repo->save($session);

    expect($result->isOk())->toBeTrue();
    expect($result->session)->not->toBeNull();
    expect($result->session->version())->toBe(1);
});

it('exists returns correct value', function () {
    $repo = makeRepo();
    $session = makeTestSession();

    expect($repo->exists(new SessionId('s1')))->toBeFalse();

    $repo->save($session);
    expect($repo->exists(new SessionId('s1')))->toBeTrue();
});

it('delete removes session', function () {
    $repo = makeRepo();
    $session = makeTestSession();
    $repo->save($session);

    $repo->delete(new SessionId('s1'));

    expect($repo->exists(new SessionId('s1')))->toBeFalse();
});

it('listHeaders returns SessionInfoList', function () {
    $repo = makeRepo();
    $repo->save(makeTestSession('s1'));
    $repo->save(makeTestSession('s2'));

    $list = $repo->listHeaders();

    expect($list)->toBeInstanceOf(SessionInfoList::class);
    expect($list->count())->toBe(2);
});
