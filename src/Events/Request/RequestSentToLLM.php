<?php
namespace Cognesy\Instructor\Events\Request;

use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;
use Psr\Log\LogLevel;

class RequestSentToLLM extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public Request $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->request->toArray());
    }
}