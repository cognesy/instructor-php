<?php
namespace Cognesy\Http\Events;

use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;
use Psr\Log\LogLevel;

final class HttpRequestSent extends Event
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
        return Json::encode($this->toArray());
    }

    public function toArray(): array {
        return [
            'url' => $this->url,
            'method' => $this->method,
            'headers' => $this->headers,
            'body' => $this->body,
        ];
    }
}