<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Support\RecordReplay\Matching\ExactHashMatcher;
use Cognesy\Http\Extras\Support\RecordReplay\Matching\RequestMatcher;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\DefaultRequestRedactor;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\RequestRedactor;
use RuntimeException;

/**
 * A repository for HTTP request/response recordings.
 *
 * @deprecated Use CassetteStore through RecordReplayMiddleware.
 */
class RequestRecords
{
    private string $storageDir;
    private RequestMatcher $matcher;
    private RequestRedactor $redactor;

    public function __construct(
        string $storageDir,
        ?RequestMatcher $matcher = null,
        ?RequestRedactor $redactor = null,
    ) {
        $this->storageDir = $storageDir;
        $this->matcher = $matcher ?? new ExactHashMatcher();
        $this->redactor = $redactor ?? new DefaultRequestRedactor();
        $this->ensureStorageDirExists();
    }

    public function save(HttpRequest $request, HttpResponse $response): string {
        // Redact sensitive request material (API keys / auth headers) BEFORE it is
        // ever written to disk — this is the persistence boundary and the last line
        // of defense against a live credential reaching a committed fixture.
        $record = RequestRecord::createAppropriate($request, $response, $this->redactor);
        $filename = $this->getFilenameForRequest($request);

        $errorMessage = null;
        set_error_handler(static function (int $severity, string $message) use (&$errorMessage): bool {
            $errorMessage = $message;
            return true;
        });
        try {
            $written = file_put_contents($filename, $record->toJson());
        } finally {
            restore_error_handler();
        }

        if ($written === false) {
            throw new RuntimeException("Failed to save HTTP interaction recording to {$filename}: " . ($errorMessage ?? 'Unknown write error'));
        }
        @chmod($filename, 0600);

        return $filename;
    }

    /** @param iterable<string> $chunks */
    public function saveStreamed(HttpRequest $request, HttpResponse $response, iterable $chunks): string {
        $requestData = [
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toString(),
            'options' => $request->options(),
        ];
        $requestData = $this->redactor->redact($requestData);

        $responseData = [
            'statusCode' => $response->statusCode(),
            'headers' => $response->headers(),
        ];
        if ($this->redactor instanceof \Cognesy\Http\Extras\Support\RecordReplay\Redaction\ResponseRedactor) {
            $responseData = $this->redactor->redactResponse($responseData);
        }

        $filename = $this->getFilenameForRequest($request);
        $temporaryFilename = $filename . '.tmp.' . bin2hex(random_bytes(8));
        $handle = fopen($temporaryFilename, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Failed to create HTTP interaction recording at {$temporaryFilename}");
        }

        try {
            $prefix = json_encode(
                ['request' => $requestData, 'response' => $responseData],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            );
            if ($prefix === false) {
                throw new RuntimeException('Failed to encode HTTP recording metadata.');
            }
            $prefix = rtrim($prefix);
            $this->writeToHandle($handle, substr($prefix, 0, -1) . ',"chunks":[');

            $first = true;
            $chunksToWrite = $this->redactor instanceof FixtureSanitizer
                ? $this->redactor->redactStream($chunks)
                : $chunks;

            foreach ($chunksToWrite as $chunk) {
                $chunkData = ['chunks' => [$chunk]];
                if ($this->redactor instanceof \Cognesy\Http\Extras\Support\RecordReplay\Redaction\ResponseRedactor
                    && !$this->redactor instanceof FixtureSanitizer) {
                    $chunkData = $this->redactor->redactResponse($chunkData);
                }
                $safeChunk = $chunkData['chunks'][0] ?? $chunk;
                $encodedChunk = json_encode((string) $safeChunk, JSON_UNESCAPED_SLASHES);
                if ($encodedChunk === false) {
                    throw new RuntimeException('Failed to encode HTTP recording chunk.');
                }
                $this->writeToHandle($handle, $first ? $encodedChunk : ',' . $encodedChunk);
                $first = false;
            }

            $this->writeToHandle($handle, ']}');
            fclose($handle);
            $handle = null;

            if (!rename($temporaryFilename, $filename)) {
                throw new RuntimeException("Failed to finalize HTTP interaction recording at {$filename}");
            }
            @chmod($filename, 0600);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (file_exists($temporaryFilename)) {
                unlink($temporaryFilename);
            }
        }

        return $filename;
    }

    public function find(HttpRequest $request): ?RequestRecord {
        $filename = $this->getFilenameForRequest($request);

        if (!file_exists($filename)) {
            return null;
        }

        $json = file_get_contents($filename);
        if ($json === false) {
            return null;
        }
        return RequestRecord::fromJson($json);
    }

    public function delete(HttpRequest $request): bool {
        $filename = $this->getFilenameForRequest($request);

        if (file_exists($filename)) {
            return unlink($filename);
        }

        return false;
    }

    public function clear(): int {
        $count = 0;
        $files = glob($this->storageDir . '/*.json');
        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    public function all(): array {
        $records = [];
        $files = glob($this->storageDir . '/*.json');
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $json = file_get_contents($file);
            if ($json === false) {
                continue;
            }
            $record = RequestRecord::fromJson($json);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    public function findStreamed(): array {
        $streamed = [];
        $records = $this->all();

        foreach ($records as $record) {
            if ($record instanceof StreamedRequestRecord) {
                $streamed[] = $record;
            }
        }

        return $streamed;
    }

    public function count(): int {
        $files = glob($this->storageDir . '/*.json');
        return is_array($files) ? count($files) : 0;
    }

    private function getFilenameForRequest(HttpRequest $request): string {
        // The record is identified solely by (is-streamed, matcher fingerprint).
        // Nothing else about the request participates in the lookup, so changing
        // the matching strategy (e.g. canonical-JSON or URL-normalizing matchers)
        // is fully honored without touching storage layout. Human-readable request
        // details live inside the JSON body; per-example namespacing (R4) restores
        // navigability far better than an in-filename slug could.
        $streamPrefix = $request->isStreamed() ? 'stream_' : '';

        return $this->storageDir . '/' .
            $streamPrefix .
            $this->matcher->fingerprint($request) .
            '.json';
    }

    private function ensureStorageDirExists(): void {
        if (!is_dir($this->storageDir)) {
            if (!mkdir($concurrentDirectory = $this->storageDir, 0700, true) && !is_dir($concurrentDirectory)) {
                throw new RuntimeException("Failed to create storage directory: {$this->storageDir}");
            }
        }
    }

    /** @param resource $handle */
    private function writeToHandle(mixed $handle, string $data): void {
        if ($data !== '' && fwrite($handle, $data) !== strlen($data)) {
            throw new RuntimeException('Failed to write HTTP interaction recording.');
        }
    }

    /** @deprecated Filesystem configuration belongs to a CassetteStore. */
    public function setStorageDir(string $dir): self {
        $this->storageDir = $dir;
        $this->ensureStorageDirExists();
        return $this;
    }

    public function getStorageDir(): string {
        return $this->storageDir;
    }
}
