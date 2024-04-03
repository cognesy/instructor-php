<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class PartialResponseGenerationFailed extends Event
{
    public function __construct(public array $errors)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->errors);
    }
}