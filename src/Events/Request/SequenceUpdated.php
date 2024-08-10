<?php

namespace Cognesy\Instructor\Events\Request;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;

class SequenceUpdated extends Event
{
    public function __construct(
        public Sequenceable $sequence
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->sequence);
    }
}
