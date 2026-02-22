<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Events;

use Cognesy\Events\Event;

final class SessionLoadFailed extends Event
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $error,
        public readonly string $errorType,
    ) {
        parent::__construct([
            'sessionId' => $this->sessionId,
            'error' => $this->error,
            'errorType' => $this->errorType,
        ]);
    }
}

