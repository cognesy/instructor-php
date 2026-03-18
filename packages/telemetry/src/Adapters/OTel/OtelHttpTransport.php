<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Adapters\OTel;

use Cognesy\Telemetry\Infrastructure\Transport\HttpTransportException;
use Cognesy\Telemetry\Infrastructure\Transport\HttpTransportResponse;
use Cognesy\Telemetry\Infrastructure\Transport\HttpTransportResponseException;
use Cognesy\Utils\Json\Json;

final readonly class OtelHttpTransport implements CanSendOtelPayloads
{
    /**
     * @param null|\Closure(string, array<string, bool|float|int|string>, string): HttpTransportResponse $sender
     */
    public function __construct(
        private OtelConfig $config,
        private ?\Closure $sender = null,
    ) {}

    #[\Override]
    public function send(string $signal, array $payload): void
    {
        $url = $this->config->endpoint() . '/v1/' . $signal;
        $response = $this->sendRequest(
            url: $url,
            headers: [
                'Content-Type' => 'application/json',
                ...$this->config->headers(),
            ],
            body: Json::encode($payload),
        );

        $this->assertSuccessfulResponse($url, $response);
    }

    private function assertSuccessfulResponse(string $url, HttpTransportResponse $response): void
    {
        if ($response->statusCode() < 400) {
            return;
        }

        throw new HttpTransportResponseException(
            statusCode: $response->statusCode(),
            body: $response->body(),
            message: $this->responseErrorMessage($url, $response->statusCode()),
        );
    }

    /** @param array<string, scalar> $headers */
    private function sendRequest(string $url, array $headers, string $body): HttpTransportResponse
    {
        if ($this->sender !== null) {
            return ($this->sender)($url, $headers, $body);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $this->formatHeaders($headers),
                'content' => $body,
                'ignore_errors' => true,
            ],
        ]);

        ['result' => $result, 'warning' => $warning] = $this->performRequest($url, $context);
        $statusCode = $this->extractStatusCode($this->responseHeaders());

        if ($statusCode === null) {
            throw new HttpTransportException($this->transportErrorMessage($url, $warning));
        }

        return new HttpTransportResponse($statusCode, is_string($result) ? $result : '');
    }

    /** @return array{result: mixed, warning: ?string} */
    private function performRequest(string $url, mixed $context): array
    {
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });

        try {
            $result = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        return ['result' => $result, 'warning' => $warning];
    }

    /** @param array<string, scalar> $headers */
    private function formatHeaders(array $headers): string
    {
        $lines = [];

        foreach ($headers as $key => $value) {
            $lines[] = $key . ': ' . (string) $value;
        }

        return implode("\r\n", $lines);
    }

    /** @param list<string> $responseHeaders */
    private function extractStatusCode(array $responseHeaders): ?int
    {
        $statusLine = $responseHeaders[0] ?? null;

        if (!is_string($statusLine)) {
            return null;
        }

        preg_match('/\s(\d{3})\s/', $statusLine, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    private function responseErrorMessage(string $url, int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => "Telemetry transport request to {$url} failed with HTTP {$statusCode} Server Error",
            default => "Telemetry transport request to {$url} failed with HTTP {$statusCode} Client Error",
        };
    }

    private function transportErrorMessage(string $url, ?string $warning): string
    {
        $detail = $warning ?? 'Unknown telemetry transport error';

        return "Telemetry transport request to {$url} failed: {$detail}";
    }

    /** @return list<string> */
    private function responseHeaders(): array
    {
        return match (function_exists('http_get_last_response_headers')) {
            true => http_get_last_response_headers() ?? [],
            default => [],
        };
    }
}
