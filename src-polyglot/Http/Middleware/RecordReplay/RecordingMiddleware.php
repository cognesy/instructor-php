<?php

namespace Cognesy\Polyglot\Http\Middleware\RecordReplay;

use Cognesy\Polyglot\Http\Contracts\CanHandleHttp;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Contracts\HttpMiddleware;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Polyglot\Http\Middleware\RecordReplay\Events\HttpInteractionRecorded;
use Cognesy\Utils\Events\EventDispatcher;

/**
 * RecordingMiddleware
 * 
 * HTTP middleware that records HTTP interactions for later replay.
 */
class RecordingMiddleware implements HttpMiddleware
{
    /**
     * @var RequestRecords Repository for storing HTTP recordings
     */
    private RequestRecords $records;
    
    /**
     * @var EventDispatcher|null Event dispatcher
     */
    private ?EventDispatcher $events;
    
    /**
     * Constructor
     * 
     * @param string $storageDir Directory to store recordings
     * @param EventDispatcher|null $events Optional event dispatcher
     */
    public function __construct(string $storageDir, ?EventDispatcher $events = null)
    {
        $this->records = new RequestRecords($storageDir);
        $this->events = $events ?? new EventDispatcher();
    }
    
    /**
     * Handle an HTTP request by recording the interaction
     * 
     * @param HttpClientRequest $request The request to handle
     * @param CanHandleHttp $next The next handler in the chain
     * @return HttpClientResponse The response
     */
    public function handle(HttpClientRequest $request, CanHandleHttp $next): HttpClientResponse
    {
        // Get actual response from next handler
        $response = $next->handle($request);
        
        // Record the interaction
        $this->records->save($request, $response);
        
        // Dispatch event
        $this->events->dispatch(new HttpInteractionRecorded($request, $response));
        
        // Return original response
        return $response;
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
