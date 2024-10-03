<?php
namespace Cognesy\Instructor\Events\ApiClient;

use Cognesy\Instructor\Utils\Json\Json;

class LLMStreamResponseReceived extends LLMResponseReceived
{
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
        parent::__construct($status, $headers, $body);
    }

    public function __toString() : string {
        return Json::encode([
            'status' => $this->status,
            'headers' => $this->headers,
            'body' => $this->body,
        ]);
    }
}
