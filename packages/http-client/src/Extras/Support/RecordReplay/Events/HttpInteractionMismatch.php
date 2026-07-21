<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay\Events;

use Cognesy\Events\Event;

final class HttpInteractionMismatch extends Event
{
    public function __construct(
        public readonly HttpInteractionSummary $interaction,
    ) {
        parent::__construct();
    }
}
