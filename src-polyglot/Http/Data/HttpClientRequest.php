<?php

namespace Cognesy\Polyglot\Http\Data;

class HttpClientRequest
{
    public function __construct(
        public string $url,
        public string $method,
        public array $headers,
        public array $body,
        public array $options,
    ) {}

    public function url() : string {
        return $this->url;
    }

    public function method() : string {
        return $this->method;
    }

    public function headers() : array {
        return $this->headers;
    }

    public function body() : array {
        return $this->body;
    }

    public function options() : array {
        return $this->options;
    }

    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }

    public function withStreaming(bool $streaming) : self {
        $this->options['stream'] = $streaming;
        return $this;
    }
}
