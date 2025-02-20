<?php

namespace Cognesy\Utils\Debug;

use Cognesy\Utils\Cli\Color;
use Cognesy\Utils\Cli\Console;
use Cognesy\Utils\Settings;
use DateTimeImmutable;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Debug
{
    public static function enable() : void {
        self::setEnabled(true);
    }

    public static function disable() : void {
        self::setEnabled(false);
    }

    public static function setEnabled(bool $debug = true) : void {
        Settings::set('debug', 'http.enabled', $debug);
    }

    public static function isEnabled() : bool {
        return Settings::get('debug', 'http.enabled', false);
    }

    public static function isFlag(string $path) : bool {
        return self::isEnabled() && Settings::get('debug', $path, false);
    }

    public static function tryDumpStream(string $line): void {
        if (Debug::isFlag('http.responseStream')) {
            $now = (new DateTimeImmutable)->format('H:i:s v') . 'ms';
            Console::print("\n[STREAM DATA]", [Color::DARK_YELLOW]);
            Console::print(" at ", [Color::DARK_GRAY]);
            Console::println("$now", [Color::GRAY]);
            Console::println($line, [Color::DARK_GRAY]);
        }
    }

    public static function tryDumpRequest(RequestInterface $request): void {
        $highlight = [Color::YELLOW];
        if (Debug::isFlag('http.requestHeaders')) {
            Console::println("[REQUEST HEADERS]", $highlight);
            self::printHeaders($request->getHeaders());
            Console::println("[/REQUEST HEADERS]", $highlight);
            Console::println("");
        }
        if (Debug::isFlag('http.requestBody')) {
            Console::println("[REQUEST BODY]", $highlight);
            self::printBody((string) $request->getBody());
            Console::println("[/REQUEST BODY]", $highlight);
            Console::println("");
        }
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
        if (Debug::isFlag('http.responseHeaders')) {
            Console::println("[RESPONSE HEADERS]", $highlight);
            self::printHeaders($response->getHeaders());
            Console::println("[/RESPONSE HEADERS]", $highlight);
            Console::println("");
        }
        if (Debug::isFlag('http.responseBody') && $options['stream'] === false) {
            Console::println("[RESPONSE BODY]", $highlight);
            self::printBody((string) $response->getBody());
            Console::println("[/RESPONSE BODY]", $highlight);
            Console::println("");
        }
    }

    private static function printHeaders(array $headers) : void {
        foreach ($headers as $name => $values) {
            Console::print("   ".$name, [Color::DARK_GRAY]);
            Console::print(': ', [Color::WHITE]);
            Console::println(implode(' | ', $values), [Color::GRAY]);
        }
    }

    private static function printBody(string $body) : void {
        /** @noinspection ForgottenDebugOutputInspection */
        dump(json_decode($body));
    }

    public static function tryDumpUrl(string $url) : void {
        if (Debug::isFlag('http.requestUrl')) {
            Console::println("");
            Console::println("[REQUEST URL]", [Color::YELLOW]);
            Console::println($url, [Color::GRAY]);
            Console::println("[REQUEST /URL]", [Color::YELLOW]);
            Console::println("");
        }
    }
}