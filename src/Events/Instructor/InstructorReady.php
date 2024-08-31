<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Container\Container;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;

class InstructorReady extends Event
{
    public function __construct(
        public Container $config
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->config);
    }
}
