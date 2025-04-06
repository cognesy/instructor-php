<?php

namespace Cognesy\Http\Enums;

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
}
