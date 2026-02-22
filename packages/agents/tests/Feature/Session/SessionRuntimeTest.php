<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Feature\Session;

use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Agents\Session\Contracts\CanStoreSessions;
use Cognesy\Agents\Session\Events\SessionActionExecuted;
use Cognesy\Agents\Session\Events\SessionLoadFailed;
use Cognesy\Agents\Session\Events\SessionLoaded;
use Cognesy\Agents\Session\Events\SessionSaveFailed;
use Cognesy\Agents\Session\Events\SessionSaved;
use Cognesy\Agents\Session\Exceptions\SessionConflictException;
use Cognesy\Agents\Session\Exceptions\SessionNotFoundException;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;
use Cognesy\Agents\Session\SessionRepository;
use Cognesy\Agents\Session\SessionRuntime;
use Cognesy\Agents\Session\Store\InMemorySessionStore;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Events\Dispatchers\EventDispatcher;

function makeRuntimeSession(string $id): AgentSession {
    return new AgentSession(
        header: AgentSessionInfo::fresh(SessionId::from($id), 'agent', 'Agent'),
        definition: new AgentDefinition(name: 'agent', description: 'test', systemPrompt: 'Test'),
        state: \Cognesy\Agents\Data\AgentState::empty(),
    );
}

function makeRuntime(): array {
    $store = new InMemorySessionStore();
    $repo = new SessionRepository($store);
    $events = new EventDispatcher('session-runtime-test');
    $runtime = new SessionRuntime($repo, $events);
    return [$runtime, $repo, $events];
}

it('executes action via load->executeOn->save and returns persisted session', function () {
    [$runtime, $repo, $events] = makeRuntime();
    $created = $repo->create(makeRuntimeSession('rt1'));
    $recorded = [];
    $events->wiretap(static function (object $event) use (&$recorded): void {
        $recorded[] = $event::class;
    });

    $action = new class implements CanExecuteSessionAction {
        public function executeOn(AgentSession $session): AgentSession {
            return $session->suspended();
        }
    };

    $updated = $runtime->execute(SessionId::from('rt1'), $action);

    expect($updated->status()->value)->toBe('suspended');
    expect($updated->version())->toBe(2);
    expect($recorded)->toBe([
        SessionLoaded::class,
        SessionActionExecuted::class,
        SessionSaved::class,
    ]);
});

it('getSession returns session without mutating version', function () {
    [$runtime, $repo] = makeRuntime();
    $created = $repo->create(makeRuntimeSession('rt2'));

    $loaded = $runtime->getSession(SessionId::from('rt2'));

    expect($loaded->sessionId())->toBe('rt2');
    expect($loaded->version())->toBe($created->version());
});

it('getSessionInfo returns header without mutating version', function () {
    [$runtime, $repo] = makeRuntime();
    $created = $repo->create(makeRuntimeSession('rt3'));

    $info = $runtime->getSessionInfo(SessionId::from('rt3'));

    expect($info->sessionId())->toBe('rt3');
    expect($info->version())->toBe($created->version());
});

it('listSessions returns headers from repository', function () {
    [$runtime, $repo] = makeRuntime();
    $repo->create(makeRuntimeSession('rt4'));
    $repo->create(makeRuntimeSession('rt5'));

    $list = $runtime->listSessions();

    expect($list->count())->toBe(2);
});

it('execute propagates not found exception and emits load failure event', function () {
    [$runtime, , $events] = makeRuntime();
    $recorded = [];
    $events->wiretap(static function (object $event) use (&$recorded): void {
        $recorded[] = $event::class;
    });

    $action = new class implements CanExecuteSessionAction {
        public function executeOn(AgentSession $session): AgentSession {
            return $session;
        }
    };

    try {
        $runtime->execute(SessionId::from('missing-runtime'), $action);
        throw new \RuntimeException('Expected SessionNotFoundException was not thrown');
    } catch (SessionNotFoundException) {
        expect($recorded)->toBe([SessionLoadFailed::class]);
    }
});

it('execute propagates conflict exception from repository save and emits save failure event', function () {
    $repo = new SessionRepository(new class implements CanStoreSessions {
        private AgentSession $stored;

        public function __construct() {
            $this->stored = makeRuntimeSession('conflict');
        }

        public function create(AgentSession $session): AgentSession {
            return $session;
        }

        public function save(AgentSession $session): AgentSession {
            throw new SessionConflictException('forced conflict');
        }

        public function load(SessionId $sessionId): ?AgentSession {
            return makeRuntimeSession($sessionId->toString());
        }

        public function exists(SessionId $sessionId): bool {
            return true;
        }

        public function delete(SessionId $sessionId): void {}

        public function listHeaders(): SessionInfoList {
            return new SessionInfoList(AgentSessionInfo::fresh(SessionId::from('conflict'), 'agent', 'Agent'));
        }
    });

    $events = new EventDispatcher('session-runtime-test');
    $recorded = [];
    $events->wiretap(static function (object $event) use (&$recorded): void {
        $recorded[] = $event::class;
    });
    $runtime = new SessionRuntime($repo, $events);

    $action = new class implements CanExecuteSessionAction {
        public function executeOn(AgentSession $session): AgentSession {
            return $session->resumed();
        }
    };

    try {
        $runtime->execute(SessionId::from('conflict'), $action);
        throw new \RuntimeException('Expected SessionConflictException was not thrown');
    } catch (SessionConflictException) {
        expect($recorded)->toBe([
            SessionLoaded::class,
            SessionActionExecuted::class,
            SessionSaveFailed::class,
        ]);
    }
});
