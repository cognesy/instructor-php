<?php

namespace Cognesy\Polyglot\Http\Data;

class HttpRequestBody
{
    public string $body;

    public function __construct(
        string|array $body,
    ) {
        $this->body = match (true) {
            is_string($body) => $body,
            is_array($body) => json_encode($body),
            default => ''
        };
    }

    public function toString() : string {
        return $this->body;
    }

    public function toArray() : array {
        return json_decode($this->body, true);
    }
}
