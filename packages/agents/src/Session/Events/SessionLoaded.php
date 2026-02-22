<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Events;

use Cognesy\Events\Event;

final class SessionLoaded extends Event
{
    public function __construct(
        public readonly string $sessionId,
        public readonly int $version,
        public readonly string $status,
    ) {
        parent::__construct([
            'sessionId' => $this->sessionId,
            'version' => $this->version,
            'status' => $this->status,
        ]);
    }
}

