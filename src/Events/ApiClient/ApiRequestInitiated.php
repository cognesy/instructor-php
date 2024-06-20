<?php

namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use Psr\Log\LogLevel;

class ApiRequestInitiated extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public array $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->request);
    }
}
