<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteCorruptException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteExhaustedException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteMismatchException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteSerializationException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteWriteException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\LegacyCassetteException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\UnsupportedCassetteVersionException;
use Cognesy\Http\Extras\Support\RecordReplay\Matching\RequestMatcher;
use Cognesy\Http\Extras\Support\RecordReplay\RecordingStream;
use Cognesy\Http\Stream\IterableStream;
use JsonException;

/**
 * Versioned filesystem cassette store with atomic interaction publication.
 *
 * Metadata is UTF-8 JSON. Request and response payloads are separate binary
 * files so invalid UTF-8 and long streamed bodies never pass through JSON.
 */
final class FilesystemCassetteStore implements CassetteStore
{
    private const MANIFEST_FILE = 'cassette.json';
    private const LOCK_FILE = '.cassette.lock';
    private const INTERACTIONS_DIR = 'interactions';

    private string $directory;
    private string $interactionsDirectory;
    private int $replayCursor = 0;

    public function __construct(
        string $directory,
        private readonly FixtureSanitizer $sanitizer,
        private readonly RequestMatcher $matcher,
    ) {
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $this->interactionsDirectory = $this->directory . DIRECTORY_SEPARATOR . self::INTERACTIONS_DIR;
        $this->ensureLayout();
    }

    public static function fromDirectory(
        string $directory,
        FixtureSanitizer $sanitizer,
        RequestMatcher $matcher,
    ): self {
        return new self($directory, $sanitizer, $matcher);
    }

    #[\Override]
    public function record(HttpRequest $request, HttpResponse $response): HttpResponse
    {
        [$sequence, $reservation] = $this->reserveSequence();

        if (!$response->isStreamed()) {
            try {
                $requestData = $this->sanitizedRequest($request);
                $responseData = $this->sanitizedResponse($response);
                $body = $responseData['body'] ?? null;
                if (!is_string($body)) {
                    throw new CassetteSerializationException('HTTP cassette response body is not a string.');
                }
                $this->publishInteraction(
                    sequence: $sequence,
                    request: $request,
                    requestData: $requestData,
                    response: $response,
                    responseHeaders: $responseData['headers'] ?? [],
                    responsePayload: $body,
                    payloadType: 'body',
                );
            } finally {
                $this->releaseReservation($reservation);
            }

            return $response;
        }

        return HttpResponse::streaming(
            statusCode: $response->statusCode(),
            headers: $response->headers(),
            stream: new RecordingStream(
                source: $response->rawStream(),
                onCompleted: function (iterable $chunks) use ($sequence, $reservation, $request, $response): void {
                    try {
                        $this->publishInteraction(
                            sequence: $sequence,
                            request: $request,
                            requestData: $this->sanitizedRequest($request),
                            response: $response,
                            responseHeaders: $this->sanitizedResponseHeaders($response),
                            responsePayload: $this->sanitizer->redactStream($chunks),
                            payloadType: 'chunks',
                        );
                    } finally {
                        $this->releaseReservation($reservation);
                    }
                },
                onAbandoned: function () use ($reservation): void {
                    $this->releaseReservation($reservation);
                },
            ),
        );
    }

    #[\Override]
    public function replay(HttpRequest $request): ?HttpResponse
    {
        $directories = $this->interactionDirectories();
        if ($directories === []) {
            return null;
        }
        if (!isset($directories[$this->replayCursor])) {
            throw new CassetteExhaustedException();
        }

        $directory = $directories[$this->replayCursor];
        $interaction = $this->readInteraction($directory);
        if ($interaction->fingerprint !== $this->matcher->fingerprint($request)) {
            throw new CassetteMismatchException();
        }

        $response = $this->materializeResponse($directory, $interaction);
        $this->replayCursor++;

        return $response;
    }

    /** @return array{0: int, 1: string} */
    private function reserveSequence(): array
    {
        $lock = $this->openLock();
        $reservation = null;
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new CassetteWriteException('HTTP cassette lock could not be acquired.');
            }

            $sequence = $this->nextSequence();
            $reservation = $this->interactionsDirectory . DIRECTORY_SEPARATOR
                . '.reserved-' . $sequence . '-' . bin2hex(random_bytes(8));
            $this->writeFile($reservation, 'reserved');
            flock($lock, LOCK_UN);
            fclose($lock);

            return [$sequence, $reservation];
        } catch (\Throwable $exception) {
            if ($reservation !== null && file_exists($reservation)) {
                unlink($reservation);
            }
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            if ($exception instanceof CassetteWriteException) {
                throw $exception;
            }
            throw new CassetteWriteException('HTTP cassette sequence reservation failed.', 0, $exception);
        }
    }

    private function nextSequence(): int
    {
        $maximum = 0;
        foreach (scandir($this->interactionsDirectory) ?: [] as $name) {
            if (preg_match('/\A(?:\.reserved-)?(\d+)(?:-|\z)/', $name, $match) === 1) {
                $maximum = max($maximum, (int) $match[1]);
            }
        }

        return $maximum + 1;
    }

    private function publishInteraction(
        int $sequence,
        HttpRequest $request,
        array $requestData,
        HttpResponse $response,
        array $responseHeaders,
        string|iterable $responsePayload,
        string $payloadType,
    ): void {
        $temporaryDirectory = $this->interactionsDirectory . DIRECTORY_SEPARATOR
            . '.tmp-' . bin2hex(random_bytes(8));
        $finalDirectory = $this->interactionsDirectory . DIRECTORY_SEPARATOR . sprintf('%06d', $sequence);
        if (!mkdir($temporaryDirectory, 0700) && !is_dir($temporaryDirectory)) {
            throw new CassetteWriteException('HTTP cassette interaction staging failed.');
        }

        try {
            $requestBody = $requestData['body'] ?? '';
            if (!is_string($requestBody)) {
                throw new CassetteSerializationException('HTTP cassette request body is not a string.');
            }
            $requestDescriptor = $this->writeBinaryPayload(
                $temporaryDirectory . DIRECTORY_SEPARATOR . 'request.body',
                $requestBody,
            );
            $responseDescriptor = match ($payloadType) {
                'body' => $this->writeBinaryPayload(
                    $temporaryDirectory . DIRECTORY_SEPARATOR . 'response.body',
                    is_string($responsePayload) ? $responsePayload : throw new CassetteSerializationException('HTTP cassette response body is not a string.'),
                ),
                'chunks' => $this->writeChunkPayload(
                    $temporaryDirectory . DIRECTORY_SEPARATOR . 'response.chunks.ndjson',
                    is_iterable($responsePayload) ? $responsePayload : throw new CassetteSerializationException('HTTP cassette response chunks are not iterable.'),
                ),
                default => throw new CassetteSerializationException('HTTP cassette response payload type is unsupported.'),
            };

            $interaction = new CassetteInteraction(
                sequence: $sequence,
                fingerprint: $this->matcher->fingerprint($request),
                method: $requestData['method'] ?? throw new CassetteSerializationException('HTTP cassette request method is missing.'),
                url: $requestData['url'] ?? throw new CassetteSerializationException('HTTP cassette request URL is missing.'),
                requestHeaders: $requestData['headers'] ?? throw new CassetteSerializationException('HTTP cassette request headers are missing.'),
                streamed: $response->isStreamed(),
                statusCode: $response->statusCode(),
                responseHeaders: $responseHeaders,
                requestBodyFile: 'request.body',
                requestBodyBytes: $requestDescriptor['bytes'],
                requestBodySha256: $requestDescriptor['sha256'],
                responsePayloadFile: $payloadType === 'body' ? 'response.body' : 'response.chunks.ndjson',
                responsePayloadType: $payloadType,
                responseBytes: $responseDescriptor['bytes'],
                responseSha256: $responseDescriptor['sha256'],
            );
            $this->writeJson($temporaryDirectory . DIRECTORY_SEPARATOR . 'interaction.json', $interaction->toArray());
            if (file_exists($finalDirectory)) {
                throw new CassetteWriteException('HTTP cassette interaction sequence is already published.');
            }
            if (!rename($temporaryDirectory, $finalDirectory)) {
                throw new CassetteWriteException('HTTP cassette interaction publication failed.');
            }
            $temporaryDirectory = '';
        } finally {
            if ($temporaryDirectory !== '' && is_dir($temporaryDirectory)) {
                $this->removeDirectory($temporaryDirectory);
            }
        }
    }

    /** @return array<string, mixed> */
    private function sanitizedRequest(HttpRequest $request): array
    {
        $data = $this->sanitizer->redact([
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toString(),
            'options' => [],
        ]);
        if (!is_string($data['url'] ?? null)
            || !is_string($data['method'] ?? null)
            || !is_array($data['headers'] ?? null)) {
            throw new CassetteSerializationException('HTTP cassette request metadata is invalid.');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function sanitizedResponse(HttpResponse $response): array
    {
        $data = $this->sanitizer->redactResponse([
            'statusCode' => $response->statusCode(),
            'headers' => $response->headers(),
            'body' => $response->body(),
        ]);
        if (!is_array($data['headers'] ?? null)) {
            throw new CassetteSerializationException('HTTP cassette response headers are invalid.');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function sanitizedResponseHeaders(HttpResponse $response): array
    {
        $data = $this->sanitizer->redactResponse([
            'statusCode' => $response->statusCode(),
            'headers' => $response->headers(),
        ]);
        if (!is_array($data['headers'] ?? null)) {
            throw new CassetteSerializationException('HTTP cassette response headers are invalid.');
        }

        return $data;
    }

    /** @return array{file: string, bytes: int, sha256: string} */
    private function writeBinaryPayload(string $filename, string $body): array
    {
        $handle = $this->openForWriting($filename);
        try {
            $this->writeAll($handle, $body);
            fflush($handle);
            if (function_exists('fsync')) {
                fsync($handle);
            }
        } finally {
            fclose($handle);
        }

        return [
            'file' => basename($filename),
            'bytes' => strlen($body),
            'sha256' => hash('sha256', $body),
        ];
    }

    /** @param iterable<string> $chunks @return array{file: string, bytes: int, sha256: string} */
    private function writeChunkPayload(string $filename, iterable $chunks): array
    {
        $handle = $this->openForWriting($filename);
        $hash = hash_init('sha256');
        $bytes = 0;
        try {
            foreach ($chunks as $chunk) {
                if (!is_string($chunk)) {
                    throw new CassetteSerializationException('HTTP cassette response chunk is not binary data.');
                }
                $encoded = base64_encode($chunk) . "\n";
                $this->writeAll($handle, $encoded);
                hash_update($hash, $chunk);
                $bytes += strlen($chunk);
            }
            fflush($handle);
            if (function_exists('fsync')) {
                fsync($handle);
            }
        } finally {
            fclose($handle);
        }

        return [
            'file' => basename($filename),
            'bytes' => $bytes,
            'sha256' => hash_final($hash),
        ];
    }

    private function materializeResponse(string $directory, CassetteInteraction $interaction): HttpResponse
    {
        if ($interaction->streamed !== ($interaction->responsePayloadType === 'chunks')) {
            throw new CassetteCorruptException('HTTP cassette stream metadata does not match its payload.');
        }
        $payload = $this->payloadPath($directory, $interaction->responsePayloadFile);
        if ($interaction->responsePayloadType === 'body') {
            $body = $this->readBinaryPayload($payload, $interaction->responseBytes, $interaction->responseSha256);
            return HttpResponse::sync($interaction->statusCode, $interaction->responseHeaders, $body);
        }

        $this->validateChunkPayload($payload, $interaction->responseBytes, $interaction->responseSha256);
        return HttpResponse::streaming(
            statusCode: $interaction->statusCode,
            headers: $interaction->responseHeaders,
            stream: new IterableStream($this->readChunkPayload($payload)),
        );
    }

    private function readBinaryPayload(string $filename, int $expectedBytes, string $expectedSha256): string
    {
        if (!is_file($filename)) {
            throw new CassetteCorruptException('HTTP cassette response payload is missing.');
        }
        $body = file_get_contents($filename);
        if ($body === false || strlen($body) !== $expectedBytes || hash('sha256', $body) !== $expectedSha256) {
            throw new CassetteCorruptException('HTTP cassette response payload failed integrity validation.');
        }

        return $body;
    }

    private function validateChunkPayload(string $filename, int $expectedBytes, string $expectedSha256): void
    {
        $handle = $this->openForReading($filename);
        $hash = hash_init('sha256');
        $bytes = 0;
        try {
            while (($line = fgets($handle)) !== false) {
                if (!str_ends_with($line, "\n")) {
                    throw new CassetteCorruptException('HTTP cassette chunk payload is truncated.');
                }
                $chunk = base64_decode(substr($line, 0, -1), true);
                if ($chunk === false) {
                    throw new CassetteCorruptException('HTTP cassette chunk payload is malformed.');
                }
                hash_update($hash, $chunk);
                $bytes += strlen($chunk);
            }
        } finally {
            fclose($handle);
        }
        if ($bytes !== $expectedBytes || hash_final($hash) !== $expectedSha256) {
            throw new CassetteCorruptException('HTTP cassette chunk payload failed integrity validation.');
        }
    }

    /** @return iterable<string> */
    private function readChunkPayload(string $filename): iterable
    {
        $handle = $this->openForReading($filename);
        try {
            while (($line = fgets($handle)) !== false) {
                if (!str_ends_with($line, "\n")) {
                    throw new CassetteCorruptException('HTTP cassette chunk payload is truncated.');
                }
                $chunk = base64_decode(substr($line, 0, -1), true);
                if ($chunk === false) {
                    throw new CassetteCorruptException('HTTP cassette chunk payload is malformed.');
                }
                yield $chunk;
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return list<string> */
    private function interactionDirectories(): array
    {
        $directories = [];
        foreach (scandir($this->interactionsDirectory) ?: [] as $name) {
            if (preg_match('/\A\d+\z/', $name) !== 1) {
                continue;
            }
            $path = $this->interactionsDirectory . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($path)) {
                throw new CassetteCorruptException('HTTP cassette interaction directory is invalid.');
            }
            $directories[(int) $name] = $path;
        }
        ksort($directories, SORT_NUMERIC);

        return array_values($directories);
    }

    private function readInteraction(string $directory): CassetteInteraction
    {
        $filename = $directory . DIRECTORY_SEPARATOR . 'interaction.json';
        $data = $this->readJson($filename);
        $interaction = CassetteInteraction::fromArray($data);
        if (sprintf('%06d', $interaction->sequence) !== basename($directory)) {
            throw new CassetteCorruptException('HTTP cassette interaction sequence is inconsistent.');
        }
        $this->payloadPath($directory, $interaction->requestBodyFile);
        $this->readBinaryPayload(
            $this->payloadPath($directory, $interaction->requestBodyFile),
            $interaction->requestBodyBytes,
            $interaction->requestBodySha256,
        );

        return $interaction;
    }

    private function payloadPath(string $directory, string $filename): string
    {
        if ($filename === basename($filename) && !str_contains($filename, "\0")) {
            return $directory . DIRECTORY_SEPARATOR . $filename;
        }

        throw new CassetteCorruptException('HTTP cassette payload path is invalid.');
    }

    private function ensureLayout(): void
    {
        if ($this->directory === '') {
            throw new CassetteWriteException('HTTP cassette directory is empty.');
        }
        $this->ensureDirectory($this->directory);
        $manifest = $this->directory . DIRECTORY_SEPARATOR . self::MANIFEST_FILE;
        $lock = $this->openLock();
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new CassetteWriteException('HTTP cassette lock could not be acquired.');
            }
            if (is_file($manifest)) {
                $storedManifest = CassetteManifest::fromArray($this->readJson($manifest));
                if ($storedManifest->fingerprintVersion !== $this->matcherFingerprintVersion()) {
                    throw new UnsupportedCassetteVersionException('HTTP cassette fingerprint version is unsupported.');
                }
            } else {
                $legacyFiles = glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
                if ($legacyFiles !== []) {
                    throw new LegacyCassetteException('Legacy HTTP recordings require explicit migration.');
                }
                $this->writeManifestAtomically($manifest);
            }
            $this->ensureDirectory($this->interactionsDirectory);
            flock($lock, LOCK_UN);
            fclose($lock);
        } catch (\Throwable $exception) {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            throw $exception;
        }
    }

    private function writeManifestAtomically(string $manifest): void
    {
        $temporary = $manifest . '.tmp.' . bin2hex(random_bytes(8));
        try {
            $this->writeJson(
                $temporary,
                (new CassetteManifest(fingerprintVersion: $this->matcherFingerprintVersion()))->toArray(),
            );
            if (!rename($temporary, $manifest)) {
                throw new CassetteWriteException('HTTP cassette manifest publication failed.');
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** @return resource */
    private function openLock(): mixed
    {
        $handle = fopen($this->directory . DIRECTORY_SEPARATOR . self::LOCK_FILE, 'c+b');
        if ($handle === false) {
            throw new CassetteWriteException('HTTP cassette lock could not be opened.');
        }
        @chmod($this->directory . DIRECTORY_SEPARATOR . self::LOCK_FILE, 0600);

        return $handle;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new CassetteWriteException('HTTP cassette directory could not be created.');
        }
        @chmod($directory, 0700);
    }

    /** @return array<string, mixed> */
    private function readJson(string $filename): array
    {
        $json = file_get_contents($filename);
        if ($json === false || $json === '') {
            throw new CassetteCorruptException('HTTP cassette metadata is missing.');
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CassetteCorruptException('HTTP cassette metadata is malformed.', 0, $exception);
        }
        if (!is_array($data)) {
            throw new CassetteCorruptException('HTTP cassette metadata has an invalid root shape.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function writeJson(string $filename, array $data): void
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (JsonException $exception) {
            throw new CassetteSerializationException('HTTP cassette metadata could not be encoded.', 0, $exception);
        }
        $this->writeFile($filename, $json . "\n");
    }

    private function writeFile(string $filename, string $data): void
    {
        $handle = $this->openForWriting($filename);
        try {
            $this->writeAll($handle, $data);
            fflush($handle);
            if (function_exists('fsync')) {
                fsync($handle);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return resource */
    private function openForWriting(string $filename): mixed
    {
        $handle = fopen($filename, 'wb');
        if ($handle === false) {
            throw new CassetteWriteException('HTTP cassette payload could not be opened for writing.');
        }
        @chmod($filename, 0600);

        return $handle;
    }

    /** @return resource */
    private function openForReading(string $filename): mixed
    {
        $handle = fopen($filename, 'rb');
        if ($handle === false) {
            throw new CassetteCorruptException('HTTP cassette payload is missing.');
        }

        return $handle;
    }

    /** @param resource $handle */
    private function writeAll(mixed $handle, string $data): void
    {
        $offset = 0;
        $length = strlen($data);
        while ($offset < $length) {
            $written = fwrite($handle, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new CassetteWriteException('HTTP cassette payload could not be written.');
            }
            $offset += $written;
        }
    }

    private function releaseReservation(string $reservation): bool
    {
        return !file_exists($reservation) || unlink($reservation);
    }

    private function matcherFingerprintVersion(): string
    {
        return method_exists($this->matcher, 'fingerprintVersion')
            ? (string) $this->matcher->fingerprintVersion()
            : 'custom-v1';
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }
            unlink($path);
        }
        rmdir($directory);
    }
}
