<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Events;

use Cognesy\Events\Event;

final class HttpInteractionFallback extends Event
{
    public function __construct(
        public readonly HttpInteractionSummary $interaction,
    ) {
        parent::__construct();
    }

    public function toConsole(): string
    {
        return sprintf(
            "[FALLBACK] %s %s",
            $this->interaction->method,
            $this->interaction->url,
        );
    }
}
