<?php

namespace Cognesy\Utils\Debug;

use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Utils\Cli\Color;
use Cognesy\Utils\Cli\Console;
use Cognesy\Utils\Settings;
use DateTimeImmutable;

class Debug
{
    private DebugConfig $config;

    public function __construct(DebugConfig $config = null) {
        $this->config = $config ?? DebugConfig::load();
    }

    public function config() : DebugConfig {
        return $this->config;
    }

    public function enable() : void {
        $this->config->httpEnabled = true;
    }

    public function disable() : void {
        $this->config->httpEnabled = false;
    }

    public static function setEnabled(bool $debug = true) : void {
        Settings::set('debug', 'http.enabled', $debug);
    }

    public static function isEnabled() : bool {
        return Settings::get('debug', 'http.enabled', false);
    }

    public function tryDumpStream(string $line, bool $isConsolidated = false): void {
        if (!$this->config->httpResponseStream) {
            return;
        }
        $now = (new DateTimeImmutable)->format('H:i:s v') . 'ms';
        if ($isConsolidated) {
            Console::print("\n[STREAM DATA (full line)]", [Color::DARK_YELLOW]);
        } else {
            Console::print("\n[STREAM DATA]", [Color::DARK_YELLOW]);
        }
        Console::print(" at ", [Color::DARK_GRAY]);
        Console::println("$now", [Color::GRAY]);
        Console::println($line, [Color::DARK_GRAY]);
    }

    public function tryDumpRequest(HttpClientRequest $request): void {
        $highlight = [Color::YELLOW];
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
    }

    public function tryDumpTrace() {
        $highlight = [Color::WHITE];
        if ($this->config->httpTrace) {
            Console::println("[HTTP DEBUG]", $highlight);
        }
    }

    public function tryDumpResponse(HttpClientResponse $response, array $options) {
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
        if ($this->config->httpResponseBody && $options['stream'] === false) {
            Console::println("[RESPONSE BODY]", $highlight);
            $this->printBody($response->body());
            Console::println("[/RESPONSE BODY]", $highlight);
            Console::println("");
        }
    }

    public function tryDumpUrl(string $url) : void {
        if ($this->config->httpRequestUrl) {
            Console::println("");
            Console::println("[REQUEST URL]", [Color::YELLOW]);
            Console::println($url, [Color::GRAY]);
            Console::println("[REQUEST /URL]", [Color::YELLOW]);
            Console::println("");
        }
    }

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