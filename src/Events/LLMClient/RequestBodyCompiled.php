<?php

namespace Cognesy\Instructor\Events\LLMClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;

class RequestBodyCompiled extends Event
{
    public function __construct(
        public array $body
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->body);
    }
}