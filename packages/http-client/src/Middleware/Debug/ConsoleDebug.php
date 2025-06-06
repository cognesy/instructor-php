<?php

namespace Cognesy\Http\Middleware\Debug;

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Utils\Cli\Color;
use Cognesy\Utils\Cli\Console;
use DateTimeImmutable;

class ConsoleDebug implements CanHandleDebug
{
    public function __construct(
        protected readonly DebugConfig $config,
    ) {}

    public function handleRequest(HttpClientRequest $request): void {
        $highlight = [Color::YELLOW];
        if ($this->config->httpRequestUrl) {
            Console::println("");
            Console::println("[REQUEST URL]", $highlight);
            Console::println($request->url(), [Color::GRAY]);
            Console::println("[REQUEST /URL]", $highlight);
            Console::println("");
        }
        if ($this->config->httpRequestHeaders) {
            Console::println("[REQUEST HEADERS]", $highlight);
            $this->printHeaders($request->headers());
            Console::println("[/REQUEST HEADERS]", $highlight);
            Console::println("");
        }
        if ($this->config->httpRequestBody) {
            Console::println("[REQUEST BODY]", $highlight);
            $this->printBody($request->body()->toString());
            Console::println("[/REQUEST BODY]", $highlight);
            Console::println("");
        }
        $highlight = [Color::WHITE];
        if ($this->config->httpTrace) {
            Console::println("[HTTP DEBUG]", $highlight);
        }
    }

    public function handleResponse(HttpClientResponse $response) : void {
        $highlight = [Color::WHITE];
        if ($this->config->httpTrace) {
            Console::println("[/HTTP DEBUG]", $highlight);
            Console::println("");
        }
        if ($this->config->httpResponseHeaders) {
            Console::println("[RESPONSE HEADERS]", $highlight);
            $this->printHeaders($response->headers());
            Console::println("[/RESPONSE HEADERS]", $highlight);
            Console::println("");
        }
        if ($this->config->httpResponseBody && !$response->isStreamed()) {
            Console::println("[RESPONSE BODY]", $highlight);
            $this->printBody($response->body());
            Console::println("[/RESPONSE BODY]", $highlight);
            Console::println("");
        }
    }

    public function handleStreamEvent(string $line): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $now = (new DateTimeImmutable)->format('H:i:s v') . 'ms';
        Console::print("\n[STREAM DATA (full line)]", [Color::DARK_YELLOW]);
        Console::print(" at ", [Color::DARK_GRAY]);
        Console::println("$now", [Color::GRAY]);
        Console::println($line, [Color::DARK_GRAY]);
    }

    public function handleStreamChunk(string $chunk): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $now = (new DateTimeImmutable)->format('H:i:s v') . 'ms';
        Console::print("\n[STREAM DATA]", [Color::DARK_YELLOW]);
        Console::print(" at ", [Color::DARK_GRAY]);
        Console::println("$now", [Color::GRAY]);
        Console::println($chunk, [Color::DARK_GRAY]);
    }

    // INTERNAL /////////////////////////////////////////////////////////

    protected function printBody(string $body) : void {
        /** @noinspection ForgottenDebugOutputInspection */
        dump(json_decode($body));
    }

    protected function printHeaders(array $headers) : void {
        foreach ($headers as $name => $values) {
            if (is_array($values)) {
                $valuesStr = implode(', ', $values);
            } else {
                $valuesStr = $values;
            }
            Console::print("   ".$name, [Color::DARK_GRAY]);
            Console::print(': ', [Color::WHITE]);
            Console::println($valuesStr, [Color::GRAY]);
        }
    }
}