<?php
namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\Events\Event;

class PartialJsonReceived extends Event
{
    public function __construct(
        public string $partialJson = '',
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return "`".$this->partialJson."`";
    }
}
