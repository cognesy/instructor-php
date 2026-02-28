<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use RuntimeException;

/**
 * A repository for HTTP request/response recordings.
 */
class RequestRecords
{
    private string $storageDir;

    public function __construct(string $storageDir) {
        $this->storageDir = $storageDir;
        $this->ensureStorageDirExists();
    }

    public function save(HttpRequest $request, HttpResponse $response): string {
        // Use the appropriate record type based on whether the request is streamed
        $record = RequestRecord::createAppropriate($request, $response);
        $filename = $this->getFilenameForRequest($request);

        file_put_contents($filename, $record->toJson());

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
        // Generate a hash based on the request details
        $hash = md5(implode('|', [
            $request->method(),
            $request->url(),
            $request->body()->toString(),
        ]));

        // Create a filename with useful info for debugging
        $urlParts = parse_url($request->url());
        $path = $urlParts['path'] ?? '';
        $pathSlug = preg_replace('/[^a-z0-9]+/i', '-', trim($path, '/'));

        if (empty($pathSlug)) {
            $pathSlug = 'root';
        }

        // Include streaming info in the filename
        $streamPrefix = $request->isStreamed() ? 'stream_' : '';

        return $this->storageDir . '/' .
            $streamPrefix .
            strtolower($request->method()) . '_' .
            $pathSlug . '_' .
            $hash . '.json';
    }

    private function ensureStorageDirExists(): void {
        if (!is_dir($this->storageDir)) {
            if (!mkdir($concurrentDirectory = $this->storageDir, 0777, true) && !is_dir($concurrentDirectory)) {
                throw new RuntimeException("Failed to create storage directory: {$this->storageDir}");
            }
        }
    }

    public function setStorageDir(string $dir): self {
        $this->storageDir = $dir;
        $this->ensureStorageDirExists();
        return $this;
    }

    public function getStorageDir(): string {
        return $this->storageDir;
    }
}
