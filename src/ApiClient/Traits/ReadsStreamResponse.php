<?php

namespace Cognesy\Instructor\ApiClient\Traits;

// Credit: https://github.com/openai-php/client/blob/main/src/Responses/StreamResponse.php
use Generator;
use Psr\Http\Message\StreamInterface;

trait ReadsStreamResponse
{
    protected function getStreamIterator(
        StreamInterface $stream,
        callable        $getData = null,
        callable        $isDone = null,
    ): Generator {
        while (!$stream->eof()) {
            $line = trim($this->readLine($stream));
            if (empty($line)) {
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
