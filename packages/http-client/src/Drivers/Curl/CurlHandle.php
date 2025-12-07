<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use CurlHandle as NativeCurlHandle;
use RuntimeException;

/**
 * CurlHandle - Resource Manager
 *
 * Owns the lifecycle of a curl handle, providing clean access to curl operations
 * with automatic cleanup via destructor.
 */
final class CurlHandle
{
    private ?NativeCurlHandle $handle;

    private function __construct(
        private readonly string $url,
        private readonly string $method,
    ) {
        $this->handle = curl_init($url);
        if ($this->handle === false) {
            throw new RuntimeException("Failed to initialize curl for URL: {$url}");
        }
    }

    public static function create(string $url, string $method): self {
        return new self($url, $method);
    }

    public function native(): NativeCurlHandle {
        if ($this->handle === null) {
            throw new RuntimeException("Curl handle already closed");
        }
        return $this->handle;
    }

    public function setOption(int $option, mixed $value): void {
        curl_setopt($this->native(), $option, $value);
    }

    public function getInfo(int $option): mixed {
        return curl_getinfo($this->native(), $option);
    }

    public function statusCode(): int {
        return (int) $this->getInfo(CURLINFO_HTTP_CODE);
    }

    public function error(): ?string {
        $errno = curl_errno($this->native());
        return $errno !== 0 ? curl_error($this->native()) : null;
    }

    public function errorCode(): int {
        return curl_errno($this->native());
    }

    public function url(): string {
        return $this->url;
    }

    public function method(): string {
        return $this->method;
    }

    public function close(): void {
        if ($this->handle !== null) {
            curl_close($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct() {
        $this->close();
    }
}
