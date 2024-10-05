<?php
namespace Cognesy\Instructor\Events\HttpClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json\Json;
use Psr\Log\LogLevel;

class RequestSentToLLM extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public string $url,
        public string $method,
        public array $headers,
        public array $body,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'url' => $this->url,
            'method' => $this->method,
            'headers' => $this->headers,
            'body' => $this->body,
        ]);
    }
}