<?php

namespace Cognesy\Instructor\Extras\LLM;

use Closure;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Inference\StreamDataReceived;
use Cognesy\Instructor\Extras\LLM\Data\LLMConfig;
use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use DateTimeImmutable;
use Generator;
use Psr\Http\Message\StreamInterface;

class StreamReader
{
    protected EventDispatcher $events;
    protected ?Closure $parser;

    public function __construct(
        private LLMConfig $config,
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
            $this->tryDebug($line);
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

    private function tryDebug(string $line): void {
        if ($this->config->debugEnabled() && $this->config->debugSection('responseBody')) {
            $now = (new DateTimeImmutable)->format('H:i:s v') . 'ms';
            Console::print("\n[STREAM DATA]", [Color::DARK_YELLOW]);
            Console::print(" at ", [Color::DARK_GRAY]);
            Console::println("$now", [Color::DARK_WHITE]);
            Console::println($line, [Color::DARK_GRAY]);
        }
    }
}
