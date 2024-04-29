<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ResponseGenerationFailed extends Event
{
    public function __construct(
        public array $errors,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->errors);
    }
}