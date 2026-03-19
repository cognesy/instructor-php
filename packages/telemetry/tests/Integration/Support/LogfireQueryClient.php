<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Tests\Integration\Support;

use Cognesy\Config\Env;
use RuntimeException;

final readonly class LogfireQueryClient
{
    private const REQUEST_TIMEOUT_SECONDS = 10;

    public function __construct(
        private string $endpoint,
        private string $readToken,
    ) {}

    public static function fromEnv(): self
    {
        return new self(
            endpoint: rtrim((string) Env::get('LOGFIRE_OTLP_ENDPOINT', ''), '/'),
            readToken: (string) Env::get('LOGFIRE_READ_TOKEN', ''),
        );
    }

    public function latestTimestampForService(string $serviceName): ?string
    {
        $response = $this->query(
            "SELECT start_timestamp FROM records WHERE service_name = '{$serviceName}' ORDER BY start_timestamp DESC LIMIT 1",
        );

        return $response['columns'][0]['values'][0] ?? null;
    }

    /** @return array<string, mixed> */
    public function query(string $sql): array
    {
        $url = $this->endpoint . '/v1/query?sql=' . rawurlencode($sql);
        $body = $this->getJson($url, ['Authorization' => 'Bearer ' . $this->readToken]);

        if (!is_array($body)) {
            throw new RuntimeException('Logfire query API did not return a JSON object.');
        }

        if (is_string($body['detail'] ?? null)) {
            throw new RuntimeException('Logfire query API error: ' . $body['detail']);
        }

        return $body;
    }

    /** @param array<string, string> $headers */
    private function getJson(string $url, array $headers): mixed
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $this->formatHeaders($headers),
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
            throw new RuntimeException('Logfire query API request failed: ' . $detail);
        }

        $decoded = json_decode(is_string($body) ? $body : '', true);

        return $decoded;
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
