<?php

namespace Cognesy\Instructor\HttpClient;

// Credit: https://github.com/openai-php/client/blob/main/src/Responses/StreamResponse.php
use Generator;
use Psr\Http\Message\StreamInterface;

trait HandlesStreamedResponses
{
    protected function getStreamIterator(
        StreamInterface $stream,
        callable        $getData = null,
        callable        $isDone = null,
    ): Generator {
        while (!$stream->eof()) {
            $line = $this->readLine($stream);
            if ($line === '') {
                continue;
            }
            if (!is_null($isDone) && $isDone($line)) {
                break;
            }
            $data = is_null($getData) ? $line : $getData($line);
            yield $data;
        }
    }

    protected function readLine(StreamInterface $stream): string {
        $buffer = '';
        while (!$stream->eof()) {
            if ('' === ($byte = $stream->read(1))) {
                return $buffer;
            }
            $buffer .= $byte;
            if ($byte === "\n") {
                break;
            }
        }
        return $buffer;
    }
}
