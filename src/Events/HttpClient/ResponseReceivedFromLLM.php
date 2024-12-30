<?php
namespace Cognesy\Instructor\Events\HttpClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;
use Psr\Log\LogLevel;

class ResponseReceivedFromLLM extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public int $statusCode,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'statusCode' => $this->statusCode
        ]);
    }
}