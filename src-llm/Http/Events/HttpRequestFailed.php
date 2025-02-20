<?php
namespace Cognesy\LLM\Http\Events;

use Cognesy\Utils\Json\Json;
use Psr\Log\LogLevel;

class HttpRequestFailed extends \Cognesy\Utils\Events\Event
{
    public $logLevel = LogLevel::ERROR;

    public function __construct(
        public string $url,
        public string $method,
        public array $headers,
        public array $body,
        public string $errors,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode([
            'url' => $this->url,
            'method' => $this->method,
            'headers' => $this->headers,
            'body' => $this->body,
            'errors' => $this->errors,
        ]);
    }
}