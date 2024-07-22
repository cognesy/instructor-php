<?php
namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;
use Psr\Log\LogLevel;

class ApiRequestSent extends Event
{
    public $logLevel = LogLevel::INFO;

    public function __construct(
        public string $uri,
        public string $method,
        public array $headers,
        public string $body,
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return Json::encode([
            'uri' => $this->uri,
            'method' => $this->method,
            'headers' => $this->headers,
            'body' => $this->body,
        ]);
    }
}
