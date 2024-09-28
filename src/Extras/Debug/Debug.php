<?php

namespace Cognesy\Instructor\Extras\Debug;

use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\Instructor\Utils\Cli\Console;
use Cognesy\Instructor\Utils\Settings;
use DateTimeImmutable;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Debug
{
    public static function setEnabled(bool $debug = true) : void {
        Settings::set('debug', 'enabled', $debug);
    }

    public static function isEnabled() : bool {
        return Settings::get('debug', 'enabled', false);
    }

    public static function isFlag(string $path) : bool {
        return self::isEnabled() && Settings::get('debug', $path, false);
    }

    public static function tryDumpStream(string $line): void {
        if (Debug::isFlag('http.response_stream')) {
            $now = (new DateTimeImmutable)->format('H:i:s v') . 'ms';
            Console::print("\n[STREAM DATA]", [Color::DARK_YELLOW]);
            Console::print(" at ", [Color::DARK_GRAY]);
            Console::println("$now", [Color::DARK_WHITE]);
            Console::println($line, [Color::DARK_GRAY]);
        }
    }

    public static function tryDumpRequest(RequestInterface $request): void {
        $highlight = [Color::YELLOW];
        Console::println("[REQUEST]", $highlight);
        if (Debug::isFlag('http.request_headers')) {
            Console::println("[REQUEST HEADERS]", $highlight);
            self::printHeaders($request->getHeaders());
            Console::println("[/REQUEST HEADERS]", $highlight);
        }
        if (Debug::isFlag('http.request_body')) {
            Console::println("[REQUEST BODY]", $highlight);
            self::printBody((string) $request->getBody());
            Console::println("[/REQUEST BODY]", $highlight);
        }
        Console::println("[/REQUEST]", $highlight);
        Console::println("");
    }

    public static function tryDumpTrace() {
        $highlight = [Color::WHITE];
        if (Debug::isFlag('http.trace')) {
            Console::println("[HTTP DEBUG]", $highlight);
        }
    }

    public static function tryDumpResponse(ResponseInterface $response, array $options) {
        $highlight = [Color::WHITE];
        if (Debug::isFlag('http.trace')) {
            Console::println("[/HTTP DEBUG]", $highlight);
            Console::println("");
        }
        Console::println("[RESPONSE]", $highlight);
        if (Debug::isFlag('http.response_headers')) {
            Console::println("[RESPONSE HEADERS]", $highlight);
            self::printHeaders($response->getHeaders());
            Console::println("[/RESPONSE HEADERS]", $highlight);
        }
        if (Debug::isFlag('http.response_body') && $options['stream'] === false) {
            Console::println("[RESPONSE BODY]", $highlight);
            self::printBody((string) $response->getBody());
            Console::println("[/RESPONSE BODY]", $highlight);
        }
        Console::println("[/RESPONSE]", $highlight);
    }

    private static function printHeaders(array $headers) : void {
        foreach ($headers as $name => $values) {
            Console::print("   ".$name, [Color::DARK_GRAY]);
            Console::print(': ', [Color::WHITE]);
            Console::println(implode(' | ', $values), [Color::GRAY]);
        }
    }

    private static function printBody(string $body) : void {
        dump(json_decode($body));
    }
}