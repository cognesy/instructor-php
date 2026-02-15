<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Events\Event;

class StopSignalReceived extends Event
{

    /**
     * @param \Cognesy\Agents\Continuation\StopReason $reason
     * @param string $message
     * @param mixed[] $context
     * @param \class-string|string|null $source
     */
    public function __construct(
        public readonly StopReason $reason,
        public readonly string $message = '',
        public readonly array $context = [],
        public readonly ?string $source = null,
    ) {
        // parent constructor
        parent::__construct(
            data: [
                'reason' => $this->reason->value,
                'message' => $this->message,
                'context' => $this->context,
                'source' => $this->source,
            ]
        );
    }
}