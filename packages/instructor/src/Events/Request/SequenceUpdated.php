<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\Request;

use Cognesy\Events\Event;
use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Utils\Json\Json;

final class SequenceUpdated extends Event
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
