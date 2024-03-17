<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\Event;

class SequenceUpdated extends Event
{
    public function __construct(
        public Sequenceable $sequence
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return json_encode($this->sequence);
    }
}
