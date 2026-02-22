<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Events;

use Cognesy\Events\Event;

final class SessionActionExecuted extends Event
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $action,
        public readonly int $beforeVersion,
        public readonly int $afterVersion,
        public readonly string $beforeStatus,
        public readonly string $afterStatus,
    ) {
        parent::__construct([
            'sessionId' => $this->sessionId,
            'action' => $this->action,
            'beforeVersion' => $this->beforeVersion,
            'afterVersion' => $this->afterVersion,
            'beforeStatus' => $this->beforeStatus,
            'afterStatus' => $this->afterStatus,
        ]);
    }
}

