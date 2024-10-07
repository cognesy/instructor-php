<?php

namespace Cognesy\Instructor\Extras\Http;

use Closure;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\StreamDataReceived;
use Cognesy\Instructor\Extras\Debug\Debug;
use Generator;

class IterableReader
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
     * @param Generator<string> $stream
     * @return Generator<string>
     */
    public function stream(Generator $stream): Generator {
        foreach ($this->readLines($stream) as $line) {
            $processedData = $this->processLine($line);
            if ($processedData !== null) {
                yield $processedData;
            }
        }
    }

    /**
     * @param Generator<string> $stream
     * @return Generator<string>
     */
    protected function readLines(Generator $stream): Generator {
        $buffer = '';
        foreach ($stream as $chunk) {
            $buffer .= $chunk;
            while (false !== ($pos = strpos($buffer, "\n"))) {
                yield substr($buffer, 0, $pos + 1);
                $buffer = substr($buffer, $pos + 1);
            }
        }
    }

    protected function processLine(string $line): ?string {
        $line = trim($line);
        if ($line === '') {
            return null;
        }
        Debug::tryDumpStream($line);
        if (false === ($data = $this->parse($line))) {
            return null;
        }
        $this->events->dispatch(new StreamDataReceived($line, $data));
        return $data;
    }

    protected function parse(string $line): string|bool {
        return match(empty($this->parser)) {
            true => $line,
            false => ($this->parser)($line),
        };
    }
}