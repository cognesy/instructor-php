<?php declare(strict_types=1);

namespace Cognesy\Agents\Session;

use Cognesy\Agents\Session\Collections\SessionInfoList;
use Cognesy\Agents\Session\Contracts\CanControlAgentSession;
use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;
use Cognesy\Agents\Session\Contracts\CanManageAgentSessions;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\AgentSessionInfo;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Session\Enums\AgentSessionStage;
use Cognesy\Agents\Session\Events\SessionActionExecuted;
use Cognesy\Agents\Session\Events\SessionLoaded;
use Cognesy\Agents\Session\Events\SessionLoadFailed;
use Cognesy\Agents\Session\Events\SessionSaved;
use Cognesy\Agents\Session\Events\SessionSaveFailed;
use Cognesy\Events\Contracts\CanHandleEvents;
use Throwable;

final readonly class SessionRuntime implements CanManageAgentSessions
{
    private CanControlAgentSession $sessionController;

    public function __construct(
        private SessionRepository $sessions,
        private CanHandleEvents $events,
        ?CanControlAgentSession $sessionController = null,
    ) {
        $this->sessionController = $sessionController ?? PassThroughSessionController::default();
    }

    #[\Override]
    public function execute(SessionId $sessionId, CanExecuteSessionAction $action): AgentSession {
        try {
            $loaded = $this->sessions->load($sessionId);
            $loaded = $this->onStage(AgentSessionStage::AfterLoad, $loaded);
            $this->emitLoad($loaded);
        } catch (Throwable $error) {
            $this->emitLoadFailed($sessionId, $error);
            throw $error;
        }

        $nextSession = $action->executeOn($loaded);
        $nextSession = $this->onStage(AgentSessionStage::AfterAction, $nextSession);
        $nextSession = $this->onStage(AgentSessionStage::BeforeSave, $nextSession);
        $this->emitExecute($loaded, $nextSession, $action);

        try {
            $saved = $this->sessions->save($nextSession);
            $saved = $this->onStage(AgentSessionStage::AfterSave, $saved);
            $this->emitSave($saved);
            return $saved;
        } catch (Throwable $error) {
            $this->emitSaveFailed($nextSession->sessionId()->value, $error);
            throw $error;
        }
    }

    #[\Override]
    public function getSession(SessionId $sessionId): AgentSession {
        try {
            $session = $this->sessions->load($sessionId);
            $session = $this->onStage(AgentSessionStage::AfterLoad, $session);
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

    private function onStage(AgentSessionStage $stage, AgentSession $session): AgentSession {
        return $this->sessionController->onStage($stage, $session);
    }

    private function emitLoad(AgentSession $session): void {
        $this->events->dispatch(new SessionLoaded(
            sessionId: $session->sessionId()->value,
            version: $session->version(),
            status: $session->status()->value,
        ));
    }

    private function emitExecute(AgentSession $before, AgentSession $after, CanExecuteSessionAction $action): void {
        $this->events->dispatch(new SessionActionExecuted(
            sessionId: $after->sessionId()->value,
            action: $action::class,
            beforeVersion: $before->version(),
            afterVersion: $after->version(),
            beforeStatus: $before->status()->value,
            afterStatus: $after->status()->value,
        ));
    }

    private function emitSave(AgentSession $session): void {
        $this->events->dispatch(new SessionSaved(
            sessionId: $session->sessionId()->value,
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
