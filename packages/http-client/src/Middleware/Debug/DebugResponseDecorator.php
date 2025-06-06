<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\BaseResponseDecorator;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Generator;

class DebugResponseDecorator extends BaseResponseDecorator
{
    private Debug $debug;
    private bool $debugEachChunk;

    public function __construct(
        HttpClientRequest  $request,
        HttpClientResponse $response,
        Debug              $debug,
        bool               $debugEachChunk = false
    ) {
        parent::__construct($request, $response);
        $this->debug = $debug;
        $this->debugEachChunk = $debugEachChunk;
    }

    public function stream(int $chunkSize = 1): Generator
    {
        $buffer = '';
        foreach ($this->response->stream($chunkSize) as $chunk) {
            $buffer = $this->handleChunk($buffer, $chunk);
            yield $chunk;
        }
    }

    // INTERNAL ///////////////////////////////////////////////////

    private function handleChunk(string $buffer, string $chunk): string
    {
        $buffer .= $chunk;
        if ($this->debugEachChunk) {
            $this->debug->handleStream($chunk, false);
        } else {
            $buffer = $this->processBuffer($buffer);
        }
        return $buffer;
    }

    private function processBuffer(string $buffer): string
    {
        if (strpos($buffer, "\n") !== false) {
            $buffer = trim($buffer);
            if ($buffer !== '') {
                $this->debug->handleStream($buffer, true);
            }
            return '';
        }
        return $buffer;
    }
}