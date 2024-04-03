<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class InstructorStarted extends Event
{
    public function __construct(
        public array $config
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->config);
    }
}