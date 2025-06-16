<?php

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use RuntimeException;

/**
 * RequestRecords
 * 
 * A repository for HTTP request/response recordings.
 * Handles saving, loading, and managing recorded HTTP interactions.
 */
class RequestRecords
{
    /**
     * @var string Directory to store recordings
     */
    private string $storageDir;
    
    /**
     * Constructor
     * 
     * @param string $storageDir Directory to store recordings
     */
    public function __construct(string $storageDir)
    {
        $this->storageDir = $storageDir;
        $this->ensureStorageDirExists();
    }
    
    /**
     * Save a recorded HTTP interaction
     * 
     * @param HttpRequest $request The HTTP request
     * @param HttpResponse $response The HTTP response
     * @return string Path to the saved recording file
     */
    public function save(HttpRequest $request, HttpResponse $response): string
    {
        // Use the appropriate record type based on whether the request is streamed
        $record = RequestRecord::createAppropriate($request, $response);
        $filename = $this->getFilenameForRequest($request);
        
        file_put_contents($filename, $record->toJson());
        
        return $filename;
    }
    
    /**
     * Find a recording for a request
     * 
     * @param \Cognesy\Http\Data\HttpRequest $request The HTTP request to find a recording for
     * @return RequestRecord|null The recorded request/response or null if not found
     */
    public function find(HttpRequest $request): ?RequestRecord
    {
        $filename = $this->getFilenameForRequest($request);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $json = file_get_contents($filename);
        $record = RequestRecord::fromJson($json);
        
        // Ensure we return the right type of record based on the request
        if ($record && $request->isStreamed() && !$record->isStreamed()) {
            // If we have a regular record but need a streamed one, return null
            // This forces re-recording with the correct type
            return null;
        }
        
        return $record;
    }
    
    /**
     * Delete a recording
     * 
     * @param \Cognesy\Http\Data\HttpRequest $request The HTTP request to delete the recording for
     * @return bool True if recording was deleted, false otherwise
     */
    public function delete(HttpRequest $request): bool
    {
        $filename = $this->getFilenameForRequest($request);
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return false;
    }
    
    /**
     * Clear all recordings
     * 
     * @return int Number of recordings deleted
     */
    public function clear(): int
    {
        $count = 0;
        $files = glob($this->storageDir . '/*.json');
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Get all recordings
     * 
     * @return RequestRecord[] Array of all recorded request/responses
     */
    public function all(): array
    {
        $records = [];
        $files = glob($this->storageDir . '/*.json');
        
        foreach ($files as $file) {
            $json = file_get_contents($file);
            $record = RequestRecord::fromJson($json);
            
            if ($record) {
                $records[] = $record;
            }
        }
        
        return $records;
    }
    
    /**
     * Find all streamed recordings
     * 
     * @return StreamedRequestRecord[] Array of streamed recordings
     */
    public function findStreamed(): array
    {
        $streamed = [];
        $records = $this->all();
        
        foreach ($records as $record) {
            if ($record instanceof StreamedRequestRecord) {
                $streamed[] = $record;
            }
        }
        
        return $streamed;
    }
    
    /**
     * Count all recordings
     * 
     * @return int Number of recordings
     */
    public function count(): int
    {
        return count(glob($this->storageDir . '/*.json'));
    }
    
    /**
     * Generate a unique filename for a request
     * 
     * @param \Cognesy\Http\Data\HttpRequest $request The request
     * @return string The filename
     */
    private function getFilenameForRequest(HttpRequest $request): string
    {
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
    
    /**
     * Ensure the storage directory exists
     * 
     * @return void
     * @throws RuntimeException If directory creation fails
     */
    private function ensureStorageDirExists(): void
    {
        if (!is_dir($this->storageDir)) {
            if (!mkdir($concurrentDirectory = $this->storageDir, 0777, true) && !is_dir($concurrentDirectory)) {
                throw new RuntimeException("Failed to create storage directory: {$this->storageDir}");
            }
        }
    }
    
    /**
     * Set the storage directory
     * 
     * @param string $dir New storage directory
     * @return self
     */
    public function setStorageDir(string $dir): self
    {
        $this->storageDir = $dir;
        $this->ensureStorageDirExists();
        return $this;
    }
    
    /**
     * Get the storage directory
     * 
     * @return string
     */
    public function getStorageDir(): string
    {
        return $this->storageDir;
    }
}
