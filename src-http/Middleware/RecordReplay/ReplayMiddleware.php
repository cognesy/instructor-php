<?php

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Middleware\RecordReplay\Events\HttpInteractionFallback;
use Cognesy\Http\Middleware\RecordReplay\Events\HttpInteractionNotFound;
use Cognesy\Http\Middleware\RecordReplay\Events\HttpInteractionReplayed;
use Cognesy\Http\Middleware\RecordReplay\Exceptions\RecordingNotFoundException;
use Cognesy\Utils\Events\EventDispatcher;

/**
 * ReplayMiddleware
 * 
 * HTTP middleware that replays recorded HTTP interactions.
 */
class ReplayMiddleware implements HttpMiddleware
{
    /**
     * @var RequestRecords Repository for HTTP recordings
     */
    private RequestRecords $records;
    
    /**
     * @var bool Whether to fallback to real requests if no recording is found
     */
    private bool $fallbackToRealRequests;
    
    /**
     * @var EventDispatcher|null Event dispatcher
     */
    private ?EventDispatcher $events;
    
    /**
     * Constructor
     * 
     * @param string $storageDir Directory with recordings
     * @param bool $fallbackToRealRequests Whether to fallback to real requests if no recording is found
     * @param EventDispatcher|null $events Optional event dispatcher
     */
    public function __construct(
        string $storageDir,
        bool $fallbackToRealRequests = true,
        ?EventDispatcher $events = null
    ) {
        $this->records = new RequestRecords($storageDir);
        $this->fallbackToRealRequests = $fallbackToRealRequests;
        $this->events = $events ?? new EventDispatcher();
    }
    
    /**
     * Handle an HTTP request by replaying a recorded response if available
     * 
     * @param \Cognesy\Http\Data\HttpClientRequest $request The request to handle
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest $next The next handler in the chain
     * @return \Cognesy\Http\Contracts\HttpClientResponse The response
     */
    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse
    {
        // Try to find a recorded response
        $record = $this->records->find($request);
        
        if ($record) {
            // Recording found, return recorded response
            $response = $record->toResponse($request->isStreamed());
            
            // Dispatch event
            $this->events->dispatch(new HttpInteractionReplayed($request, $response));
            
            return $response;
        }
        
        // No recording found, decide what to do
        if (!$this->fallbackToRealRequests) {
            // Dispatch event
            $this->events->dispatch(new HttpInteractionNotFound($request));
            
            // No fallback, throw exception
            throw new RecordingNotFoundException(
                "No recording found for request: {$request->method()} {$request->url()}"
            );
        }
        
        // Fallback to real request
        $this->events->dispatch(new HttpInteractionFallback($request));
        return $next->handle($request);
    }
    
    /**
     * Get the records repository
     * 
     * @return RequestRecords
     */
    public function getRecords(): RequestRecords
    {
        return $this->records;
    }
    
    /**
     * Set whether to fallback to real requests
     * 
     * @param bool $fallback Whether to fallback
     * @return self
     */
    public function setFallbackToRealRequests(bool $fallback): self
    {
        $this->fallbackToRealRequests = $fallback;
        return $this;
    }
    
    /**
     * Get whether fallback to real requests is enabled
     * 
     * @return bool
     */
    public function getFallbackToRealRequests(): bool
    {
        return $this->fallbackToRealRequests;
    }
    
    /**
     * Set the storage directory
     * 
     * @param string $dir New storage directory
     * @return self
     */
    public function setStorageDir(string $dir): self
    {
        $this->records->setStorageDir($dir);
        return $this;
    }
}
