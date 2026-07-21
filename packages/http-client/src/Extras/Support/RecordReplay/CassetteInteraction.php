<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteCorruptException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\UnsupportedCassetteVersionException;

final readonly class CassetteInteraction
{
    public const SCHEMA = 'instructor-http-interaction';
    public const VERSION = 1;

    /**
     * @param array<string, mixed> $requestHeaders
     * @param array<string, mixed> $responseHeaders
     */
    public function __construct(
        public int $sequence,
        public string $fingerprint,
        public string $method,
        public string $url,
        public array $requestHeaders,
        public bool $streamed,
        public int $statusCode,
        public array $responseHeaders,
        public string $requestBodyFile,
        public int $requestBodyBytes,
        public string $requestBodySha256,
        public string $responsePayloadFile,
        public string $responsePayloadType,
        public int $responseBytes,
        public string $responseSha256,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $schema = $data['schema'] ?? null;
        $version = $data['version'] ?? null;
        if ($schema !== self::SCHEMA || !is_int($version)) {
            throw new CassetteCorruptException('HTTP cassette interaction is malformed.');
        }
        if ($version !== self::VERSION) {
            throw new UnsupportedCassetteVersionException('HTTP cassette interaction version is unsupported.');
        }

        $request = $data['request'] ?? null;
        $response = $data['response'] ?? null;
        if (!is_array($request) || !is_array($response)) {
            throw new CassetteCorruptException('HTTP cassette interaction is missing request or response metadata.');
        }

        $sequence = $data['sequence'] ?? null;
        $fingerprint = $data['fingerprint'] ?? null;
        $method = $request['method'] ?? null;
        $url = $request['url'] ?? null;
        $requestHeaders = $request['headers'] ?? null;
        $streamed = $response['streamed'] ?? null;
        $statusCode = $response['statusCode'] ?? null;
        $responseHeaders = $response['headers'] ?? null;
        $requestBody = $request['body'] ?? null;
        $responsePayload = $response['payload'] ?? null;

        if (!is_int($sequence) || $sequence < 1
            || !is_string($fingerprint) || $fingerprint === ''
            || !is_string($method) || $method === ''
            || !is_string($url) || $url === ''
            || !is_array($requestHeaders)
            || !is_bool($streamed)
            || !is_int($statusCode) || $statusCode < 100 || $statusCode > 599
            || !is_array($responseHeaders)
            || !is_array($requestBody)
            || !is_array($responsePayload)) {
            throw new CassetteCorruptException('HTTP cassette interaction has invalid metadata.');
        }

        self::validatePayloadDescriptor($requestBody, 'request body');
        self::validatePayloadDescriptor($responsePayload, 'response payload');
        $payloadType = $responsePayload['type'] ?? null;
        if (!in_array($payloadType, ['body', 'chunks'], true)) {
            throw new CassetteCorruptException('HTTP cassette response payload type is invalid.');
        }

        return new self(
            sequence: $sequence,
            fingerprint: $fingerprint,
            method: $method,
            url: $url,
            requestHeaders: $requestHeaders,
            streamed: $streamed,
            statusCode: $statusCode,
            responseHeaders: $responseHeaders,
            requestBodyFile: $requestBody['file'],
            requestBodyBytes: $requestBody['bytes'],
            requestBodySha256: $requestBody['sha256'],
            responsePayloadFile: $responsePayload['file'],
            responsePayloadType: $payloadType,
            responseBytes: $responsePayload['bytes'],
            responseSha256: $responsePayload['sha256'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'sequence' => $this->sequence,
            'fingerprint' => $this->fingerprint,
            'request' => [
                'method' => $this->method,
                'url' => $this->url,
                'headers' => $this->requestHeaders,
                'body' => [
                    'file' => $this->requestBodyFile,
                    'bytes' => $this->requestBodyBytes,
                    'sha256' => $this->requestBodySha256,
                ],
            ],
            'response' => [
                'statusCode' => $this->statusCode,
                'headers' => $this->responseHeaders,
                'streamed' => $this->streamed,
                'payload' => [
                    'type' => $this->responsePayloadType,
                    'file' => $this->responsePayloadFile,
                    'bytes' => $this->responseBytes,
                    'sha256' => $this->responseSha256,
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $descriptor */
    private static function validatePayloadDescriptor(array $descriptor, string $name): void
    {
        $file = $descriptor['file'] ?? null;
        $bytes = $descriptor['bytes'] ?? null;
        $sha256 = $descriptor['sha256'] ?? null;
        if (!is_string($file) || !self::isSafeRelativePath($file)
            || !is_int($bytes) || $bytes < 0
            || !is_string($sha256) || preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1) {
            throw new CassetteCorruptException("HTTP cassette {$name} descriptor is invalid.");
        }
    }

    private static function isSafeRelativePath(string $path): bool
    {
        return $path !== '' && $path === basename($path) && !str_contains($path, "\0");
    }
}
