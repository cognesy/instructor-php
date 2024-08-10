<?php
namespace Cognesy\Instructor\Events\Request;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;
use Psr\Log\LogLevel;

class ResponseReceivedFromLLM extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public ApiResponse $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}