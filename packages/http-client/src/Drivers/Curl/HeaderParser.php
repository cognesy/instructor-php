<?php

declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

/**
 * HeaderParser - Header Parsing Logic
 *
 * Stateful parser for HTTP response headers received from curl.
 * Used via CURLOPT_HEADERFUNCTION callback.
 */
final class HeaderParser
{
    private array $headers = [];

    private int $statusCode = 0;

    public function parse(string $headerLine): void
    {
        $line = trim($headerLine);

        if ($line === '') {
            return;
        }

        if (str_starts_with($line, 'HTTP/')) {
            if (! preg_match('/^HTTP\/\S+\s+([1-9]\d{2})\b/', $line, $matches)) {
                return;
            }

            $code = (int) $matches[1];
            $this->headers = [];
            $this->statusCode = intdiv($code, 100) === 1 ? 0 : $code;

            return;
        }

        if ($this->statusCode === 0) {
            return;
        }

        $parts = explode(':', $line, 2);
        if (count($parts) !== 2) {
            return;
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        if (! isset($this->headers[$name])) {
            $this->headers[$name] = [];
        }
        $this->headers[$name][] = $value;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function reset(): void
    {
        $this->headers = [];
        $this->statusCode = 0;
    }
}
