<?php

namespace Cognesy\Instructor\Extras\Http;

use Closure;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\StreamDataReceived;
use Cognesy\Instructor\Extras\Debug\Debug;
use Generator;
use Psr\Http\Message\StreamInterface;

class StreamReader
{
    protected EventDispatcher $events;
    protected ?Closure $parser;

    public function __construct(
        ?Closure $parser = null,
        ?EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->parser = $parser;
    }

    /**
     * @return Generator<string>
     */
    public function stream(StreamInterface $stream): Generator {
        while (!$stream->eof()) {
            if ('' === ($line = trim($this->readLine($stream)))) {
                continue;
            }
            Debug::tryDumpStream($line);
            if (false === ($data = $this->parse($line))) {
                break;
            }
            $this->events->dispatch(new StreamDataReceived($line, $data));
            yield $data;
        }
    }

    protected function parse(string $line): string|bool {
        return match(empty($this->parser)) {
            true => $line,
            false => ($this->parser)($line),
        };
    }

    protected function readLine(StreamInterface $stream): string {
        $buffer = '';
        while (!$stream->eof()) {
            if ('' === ($byte = $stream->read(1))) {
                return $buffer;
            }
            $buffer .= $byte;
            if ("\n" === $byte) {
                break;
            }
        }
        return $buffer;
    }
}
