<?php

namespace Cognesy\Http\Data;

/**
 * Class HttpRequestBody
 *
 * Represents the body of an HTTP request
 */
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

    /**
     * Get the request body as a string
     *
     * @return string
     */
    public function toString() : string {
        return $this->body;
    }

    /**
     * Get the request body as an array
     *
     * @return array
     */
    public function toArray() : array {
        if (empty($this->body)) {
            return [];
        }
        // check if the body is a valid JSON string
        $data =  json_decode($this->body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        return $data;
    }
}
