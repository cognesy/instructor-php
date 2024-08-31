<?php

namespace Cognesy\Instructor\Events\Container;

use Cognesy\Instructor\Container\ComponentConfig;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;

class ComponentRequested extends Event
{
    public function __construct(
        public ComponentConfig $config
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode($this->config);
    }
}
