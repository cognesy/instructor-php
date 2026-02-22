<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Agents\Session\Contracts\CanRunSessionRuntime;
use Cognesy\Agents\Session\Events\SessionActionExecuted;
use Cognesy\Agents\Session\Events\SessionLoadFailed;
use Cognesy\Agents\Session\Events\SessionLoaded;
use Cognesy\Agents\Session\Events\SessionSaveFailed;
use Cognesy\Agents\Session\Events\SessionSaved;
use Cognesy\Events\Contracts\CanHandleEvents;
use Throwable;

final readonly class SessionRuntime implements CanRunSessionRuntime
{
    public function __construct(
        private SessionRepository $sessions,
        private CanHandleEvents $events,
    ) {}

    #[\Override]
    public function execute(SessionId $sessionId, CanExecuteSessionAction $action): AgentSession {
        try {
            $loaded = $this->sessions->load($sessionId);
            $this->emitLoad($loaded);
        } catch (Throwable $error) {
            $this->emitLoadFailed($sessionId, $error);
            throw $error;
        }

        $nextSession = $action->executeOn($loaded);
        $this->emitExecute($loaded, $nextSession, $action);

        try {
            $saved = $this->sessions->save($nextSession);
            $this->emitSave($saved);
            return $saved;
        } catch (Throwable $error) {
            $this->emitSaveFailed($nextSession->sessionId(), $error);
            throw $error;
        }
    }

    #[\Override]
    public function getSession(SessionId $sessionId): AgentSession {
        try {
            $session = $this->sessions->load($sessionId);
            $this->emitLoad($session);
            return $session;
        } catch (Throwable $error) {
            $this->emitLoadFailed($sessionId, $error);
            throw $error;
        }
    }

    #[\Override]
    public function getSessionInfo(SessionId $sessionId): AgentSessionInfo {
        return $this->getSession($sessionId)->info();
    }

    #[\Override]
    public function listSessions(): SessionInfoList {
        return $this->sessions->listHeaders();
    }

    // PRIVATE /////////////////////////////////////////////////////

    private function emitLoad(AgentSession $session): void {
        $this->events->dispatch(new SessionLoaded(
            sessionId: $session->sessionId(),
            version: $session->version(),
            status: $session->status()->value,
        ));
    }

    private function emitExecute(AgentSession $before, AgentSession $after, CanExecuteSessionAction $action): void {
        $this->events->dispatch(new SessionActionExecuted(
            sessionId: $after->sessionId(),
            action: $action::class,
            beforeVersion: $before->version(),
            afterVersion: $after->version(),
            beforeStatus: $before->status()->value,
            afterStatus: $after->status()->value,
        ));
    }

    private function emitSave(AgentSession $session): void {
        $this->events->dispatch(new SessionSaved(
            sessionId: $session->sessionId(),
            version: $session->version(),
            status: $session->status()->value,
        ));
    }

    private function emitLoadFailed(SessionId $sessionId, Throwable $error): void {
        $this->events->dispatch(new SessionLoadFailed(
            sessionId: $sessionId->toString(),
            error: $error->getMessage(),
            errorType: $error::class,
        ));
    }

    private function emitSaveFailed(string $sessionId, Throwable $error): void {
        $this->events->dispatch(new SessionSaveFailed(
            sessionId: $sessionId,
            error: $error->getMessage(),
            errorType: $error::class,
        ));
    }
}
