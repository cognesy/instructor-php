<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\BaseResponseDecorator;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Generator;

class DebugResponseDecorator extends BaseResponseDecorator
{
    private \Cognesy\Http\Debug\Debug $debug;
    private bool $streamByLine;

    public function __construct(
        HttpClientRequest          $request,
        HttpClientResponse         $response,
        ?\Cognesy\Http\Debug\Debug $debug = null
    ) {
        parent::__construct($request, $response);
        $this->debug = $debug ?? new \Cognesy\Http\Debug\Debug();
        $this->streamByLine = $this->debug->config()->httpResponseStreamByLine;
    }

    public function stream(int $chunkSize = 1): Generator
    {
        $buffer = '';
        foreach ($this->response->stream($chunkSize) as $chunk) {
            $buffer = $this->handleChunk($buffer, $chunk);
            yield $chunk;
        }
    }

    private function handleChunk(string $buffer, string $chunk): string
    {
        $buffer .= $chunk;
        if (!$this->streamByLine) {
            $this->debug->tryDumpStream($chunk, false);
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
                $this->debug->tryDumpStream($buffer, true);
            }
            return '';
        }
        return $buffer;
    }
}