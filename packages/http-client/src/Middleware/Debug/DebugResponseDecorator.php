<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;
use Generator;

class DebugResponseDecorator extends BaseResponseDecorator
{
    private Debug $debug;
    private bool $debugEachChunk;

    public function __construct(
        HttpClientRequest  $request,
        HttpClientResponse $response,
        Debug              $debug,
    ) {
        parent::__construct($request, $response);
        $this->debug = $debug;
        $this->debugEachChunk = !$debug->config()->httpResponseStreamByLine;
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
            $this->debug->handleStreamChunk($chunk);
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
                $this->debug->handleStreamEvent($buffer);
            }
            return '';
        }
        return $buffer;
    }
}