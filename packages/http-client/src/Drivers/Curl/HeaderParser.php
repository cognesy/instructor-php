<?php declare(strict_types=1);

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

    public function parse(string $headerLine): void {
        $line = trim($headerLine);

        if ($line === '') {
            return;
        }

        // Parse status line (HTTP/1.1 200 OK)
        if (str_starts_with($line, 'HTTP/')) {
            $parts = explode(' ', $line, 3);
            if (count($parts) >= 2) {
                $code = (int) $parts[1];
                if ($code > 0) {
                    $this->statusCode = $code;
                }
            }
            return;
        }

        // Parse header (Content-Type: application/json)
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);

            // Headers can appear multiple times (e.g., Set-Cookie)
            if (!isset($this->headers[$name])) {
                $this->headers[$name] = [];
            }
            $this->headers[$name][] = $value;
        }
    }

    public function headers(): array {
        return $this->headers;
    }

    public function statusCode(): int {
        return $this->statusCode;
    }

    public function reset(): void {
        $this->headers = [];
        $this->statusCode = 0;
    }
}
