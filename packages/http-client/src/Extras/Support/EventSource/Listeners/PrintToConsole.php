<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\EventSource\Listeners;

use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\DefaultRequestRedactor;
use Cognesy\Utils\Cli\Color;
use Cognesy\Utils\Cli\Console;
use DateTimeImmutable;

class PrintToConsole implements CanListenToHttpEvents
{
    /** @var array<string, int> */
    private array $printedStreamBytes = [];

    public function __construct(
        protected readonly DebugConfig $config,
    ) {}

    // INTERNAL /////////////////////////////////////////////////////////

    protected function printBody(string $body) : void {
        $body = DefaultRequestRedactor::redactBody(
            $body,
        );
        $body = substr($body, 0, max(0, $this->config->httpBodyMaxBytes));
        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Console::println($body, [Color::GRAY]);
            return;
        }

        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($pretty === false) {
            Console::println($body, [Color::GRAY]);
            return;
        }

        Console::println($pretty, [Color::GRAY]);
    }

    protected function printHeaders(array $headers) : void {
        $headers = DefaultRequestRedactor::redactHeaders($headers);
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

    #[\Override]
    public function onRequestReceived(HttpRequest $request): void {
        $highlight = [Color::YELLOW];
        if ($this->config->httpRequestUrl) {
            Console::println("");
            Console::println("[REQUEST URL]", $highlight);
            Console::println(DefaultRequestRedactor::redactUrl($request->url()), [Color::GRAY]);
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

    #[\Override]
    public function onStreamChunkReceived(HttpRequest $request, HttpResponse $response, string $chunk): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $now = (new DateTimeImmutable)->format('H:i:s v') . 'ms';
        Console::print("\n[STREAM DATA]", [Color::DARK_YELLOW]);
        Console::print(" at ", [Color::DARK_GRAY]);
        Console::println("$now", [Color::GRAY]);
        $safeChunk = $this->safeStreamPayload($request, $chunk);
        if ($safeChunk === null) {
            return;
        }
        Console::println($safeChunk, [Color::DARK_GRAY]);
    }

    #[\Override]
    public function onStreamEventAssembled(HttpRequest $request, HttpResponse $response, string $line): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $now = (new DateTimeImmutable)->format('H:i:s v') . 'ms';
        Console::print("\n[STREAM DATA (full line)]", [Color::DARK_YELLOW]);
        Console::print(" at ", [Color::DARK_GRAY]);
        Console::println("$now", [Color::GRAY]);
        $safeLine = $this->safeStreamPayload($request, $line);
        if ($safeLine === null) {
            return;
        }
        Console::println($safeLine, [Color::DARK_GRAY]);
    }

    #[\Override]
    public function onResponseReceived(HttpRequest $request, HttpResponse $response): void {
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

    private function safeStreamPayload(HttpRequest $request, string $payload): ?string {
        $limit = max(0, $this->config->httpBodyMaxBytes);
        $printed = $this->printedStreamBytes[$request->id] ?? 0;
        if ($printed >= $limit) {
            return null;
        }

        $payload = DefaultRequestRedactor::redactBody($payload);
        $payload = substr($payload, 0, $limit - $printed);
        if ($payload === '') {
            return null;
        }

        $this->printedStreamBytes[$request->id] = $printed + strlen($payload);
        return $payload;
    }
}
