<?php

namespace Cognesy\Http\Enums;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Drivers\GuzzleDriver;
use Cognesy\Http\Drivers\LaravelDriver;
use Cognesy\Http\Drivers\SymfonyDriver;
use Cognesy\Utils\Events\EventDispatcher;
use Exception;

/**
 * Enum HttpClientType
 *
 * This enum represents different types of HTTP clients that can be used
 * within the application. Each case corresponds to a specific HTTP client
 * implementation.
 *
 * Enum Cases:
 * - Guzzle: Represents the Guzzle HTTP client.
 * - Symfony: Represents the Symfony HTTP client.
 * - Laravel: Represents the Laravel HTTP client.
 * - Custom: Represents a custom HTTP client.
 */
enum HttpClientType : string
{
    case Guzzle = 'guzzle';
    case Symfony = 'symfony';
    case Laravel = 'laravel';
    case Custom = 'custom';

    public function make(?HttpClientConfig $config = null, ?EventDispatcher $events = null) : CanHandleHttpRequest {
        return match ($this) {
            self::Guzzle => self::guzzle($config, $events),
            self::Symfony => self::symfony($config, $events),
            self::Laravel => self::laravel($config, $events),
            self::Custom => throw new Exception('Custom HTTP client not implemented yet'),
        };
    }

    public static function guzzle(?HttpClientConfig $config = null, ?EventDispatcher $events = null) : CanHandleHttpRequest
    {
        return new GuzzleDriver(
            config: $config ?? new HttpClientConfig('guzzle'),
            events: $events ?? new EventDispatcher(),
        );
    }

    public static function laravel(?HttpClientConfig $config = null, ?EventDispatcher $events = null) : CanHandleHttpRequest
    {
        return new LaravelDriver(
            config: $config ?? new HttpClientConfig('laravel'),
            events: $events ?? new EventDispatcher(),
        );
    }

    public static function symfony(?HttpClientConfig $config = null, ?EventDispatcher $events = null) : CanHandleHttpRequest
    {
        return new SymfonyDriver(
            config: $config ?? new HttpClientConfig('symfony'),
            events: $events ?? new EventDispatcher(),
        );
    }
}
