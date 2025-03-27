<?php

namespace Cognesy\Polyglot\Http\Middleware\Debug;

use Cognesy\Polyglot\Http\BaseResponseDecorator;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Utils\Debug\Debug;
use Generator;

class DebugResponseDecorator extends BaseResponseDecorator
{
    private Debug $debug;
    private bool $streamByLine;

    public function __construct(
        HttpClientRequest $request,
        HttpClientResponse $response,
        ?Debug $debug = null
    ) {
        parent::__construct($request, $response);
        $this->debug = $debug ?? new Debug();
        $this->streamByLine = $this->debug->config()->httpResponseStreamByLine;
    }

    public function body(): string {
        $response = $this->response;
        $body = $response->body();
        $this->debug->tryDumpResponse($response, ['stream' => $this->request->isStreamed()]);
        return $body;
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