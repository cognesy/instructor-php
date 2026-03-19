<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Tests\Integration\Support;

use Cognesy\Config\Env;
use RuntimeException;

final readonly class LangfuseQueryClient
{
    private const DEFAULT_LOOKBACK_SECONDS = 600;
    private const REQUEST_TIMEOUT_SECONDS = 10;

    public function __construct(
        private string $baseUrl,
        private string $publicKey,
        private string $secretKey,
    ) {}

    public static function fromEnv(): self
    {
        return new self(
            baseUrl: rtrim((string) Env::get('LANGFUSE_BASE_URL', ''), '/'),
            publicKey: (string) Env::get('LANGFUSE_PUBLIC_KEY', ''),
            secretKey: (string) Env::get('LANGFUSE_SECRET_KEY', ''),
        );
    }

    /** @return null|array{id: string, timestamp: string, name: string} */
    public function latestTraceMatching(string $fragment, ?int $lookbackSeconds = null): ?array
    {
        $traces = $this->traces($lookbackSeconds);

        foreach ($traces as $trace) {
            $input = json_encode($trace['input'] ?? [], JSON_UNESCAPED_SLASHES);
            if (!is_string($input) || !str_contains($input, $fragment)) {
                continue;
            }

            $id = $trace['id'] ?? null;
            $timestamp = $trace['timestamp'] ?? null;
            $name = $trace['name'] ?? null;

            if (!is_string($id) || !is_string($timestamp) || !is_string($name)) {
                continue;
            }

            return ['id' => $id, 'timestamp' => $timestamp, 'name' => $name];
        }

        return null;
    }

    /** @return null|array{id: string, timestamp: string, name: string} */
    public function latestTraceNamed(string $name, ?int $lookbackSeconds = null): ?array
    {
        $traces = $this->traces($lookbackSeconds, $name);

        foreach ($traces as $trace) {
            $id = $trace['id'] ?? null;
            $timestamp = $trace['timestamp'] ?? null;
            $traceName = $trace['name'] ?? null;

            if (!is_string($id) || !is_string($timestamp) || $traceName !== $name) {
                continue;
            }

            return ['id' => $id, 'timestamp' => $timestamp, 'name' => $traceName];
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function traces(?int $lookbackSeconds = null, ?string $name = null): array
    {
        $url = $this->baseUrl
            . '/api/public/traces?fromTimestamp=' . rawurlencode(
                $this->lookbackFrom($lookbackSeconds ?? self::DEFAULT_LOOKBACK_SECONDS),
            )
            . '&orderBy=timestamp.desc&limit=100';
        $url = match ($name) {
            null => $url,
            default => $url . '&name=' . rawurlencode($name),
        };
        $payload = $this->getJson($url);

        return match (is_array($payload['data'] ?? null)) {
            true => $payload['data'],
            default => throw new RuntimeException('Langfuse traces API did not return a data array.'),
        };
    }

    /** @return array<string, mixed> */
    private function getJson(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $this->formatHeaders([
                    'Authorization' => 'Basic ' . base64_encode($this->publicKey . ':' . $this->secretKey),
                ]),
                'ignore_errors' => true,
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
            ],
        ]);

        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });

        try {
            $body = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        if (!is_string($body)) {
            $detail = $warning ?? 'Unknown network error';
            throw new RuntimeException('Langfuse traces API request failed: ' . $detail);
        }

        $decoded = json_decode(is_string($body) ? $body : '', true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Langfuse traces API did not return a JSON object.');
        }

        if (is_string($decoded['message'] ?? null)) {
            throw new RuntimeException('Langfuse traces API error: ' . $decoded['message']);
        }

        return $decoded;
    }

    private function lookbackFrom(int $lookbackSeconds): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', time() - max(1, $lookbackSeconds));
    }

    /** @param array<string, string> $headers */
    private function formatHeaders(array $headers): string
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        return implode("\r\n", $lines);
    }
}
