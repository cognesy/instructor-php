<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Events;

use Cognesy\Events\Event;

final class HttpInteractionRecorded extends Event
{
    public function __construct(
        public readonly HttpInteractionSummary $interaction,
    ) {
        parent::__construct();
    }

    public function toConsole(): string
    {
        return sprintf(
            "[RECORDED] %s %s => HTTP %d",
            $this->interaction->method,
            $this->interaction->url,
            $this->interaction->statusCode ?? 0,
        );
    }
}
