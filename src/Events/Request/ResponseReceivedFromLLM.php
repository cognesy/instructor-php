<?php
namespace Cognesy\Instructor\Events\Request;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Extras\LLM\Data\LLMApiResponse;
use Cognesy\Instructor\Utils\Json\Json;
use Psr\Log\LogLevel;

class ResponseReceivedFromLLM extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public LLMApiResponse $response,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}