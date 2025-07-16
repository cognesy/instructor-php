<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Middleware\RecordReplay\Events\HttpInteractionRecorded;
use Psr\EventDispatcher\EventDispatcherInterface;

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
    private ?EventDispatcherInterface $events;
    
    /**
     * Constructor
     * 
     * @param string $storageDir Directory to store recordings
     * @param EventDispatcher|null $events Optional event dispatcher
     */
    public function __construct(
        string $storageDir,
        ?EventDispatcherInterface $events = null
    ) {
        $this->records = new RequestRecords($storageDir);
        $this->events = $events ?? new EventDispatcher();
    }
    
    /**
     * Handle an HTTP request by recording the interaction
     * 
     * @param \Cognesy\Http\Data\HttpRequest $request The request to handle
     * @param \Cognesy\Http\Contracts\CanHandleHttpRequest $next The next handler in the chain
     * @return \Cognesy\Http\Contracts\HttpResponse The response
     */
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
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
