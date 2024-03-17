<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\Event;

class SequenceUpdated extends Event
{
    public function __construct(
        public Sequenceable $items
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return json_encode($this->items);
    }
}
