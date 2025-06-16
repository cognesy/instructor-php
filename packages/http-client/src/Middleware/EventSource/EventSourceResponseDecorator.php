<?php

namespace Cognesy\Http\Middleware\EventSource;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;

class EventSourceResponseDecorator extends BaseResponseDecorator
{
    public function __construct(
        HttpRequest            $request,
        HttpResponse           $response,
        private readonly array $listeners,
    ) {
        parent::__construct($request, $response);
    }

    public function stream(?int $chunkSize = null): iterable {
        $buffer = '';
        foreach ($this->response->stream($chunkSize) as $chunk) {
            $buffer = $this->handleChunk($buffer, $chunk);
            yield $chunk;
        }
    }

    // INTERNAL ///////////////////////////////////////////////////

    private function handleChunk(string $buffer, string $chunk): string {
        $buffer .= $chunk;
        foreach ($this->listeners as $listener) {
            $listener->onStreamChunkReceived($this->request, $this->response, $chunk);
        }
        $buffer = $this->processBuffer($buffer);
        return $buffer;
    }

    private function processBuffer(string $buffer): string {
        if (strpos($buffer, "\n") !== false) {
            $buffer = trim($buffer);
            if ($buffer !== '') {
                foreach ($this->listeners as $listener) {
                    $listener->onStreamEventAssembled($this->request, $this->response, $buffer);
                }
            }
            return '';
        }
        return $buffer;
    }
}