<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Transport;

use InvalidArgumentException;

final readonly class BatchTransportUrl
{
    public static function assertSecure(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $loopback = $scheme === 'http' && in_array($host, ['127.0.0.1', '[::1]', 'localhost'], true);
        if (!filter_var($url, FILTER_VALIDATE_URL) || ($scheme !== 'https' && !$loopback)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            throw new InvalidArgumentException('Batch URL must use HTTPS or a local loopback host.');
        }
    }
}
