<?php

namespace Cognesy\Polyglot\Http\Middleware\RecordReplay;

use Cognesy\Polyglot\Http\Contracts\CanHandleHttp;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Contracts\HttpMiddleware;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Cognesy\Utils\Events\EventDispatcher;
use InvalidArgumentException;
use RuntimeException;

/**
 * RecordReplayMiddleware
 * 
 * HTTP middleware that can record HTTP interactions and replay them.
 * This is a facade that delegates to specialized middleware based on mode.
 */
class RecordReplayMiddleware implements HttpMiddleware
{
    /**
     * Available modes for the middleware
     */
    public const MODE_PASS = 'pass';    // Pass through without recording/replaying
    public const MODE_RECORD = 'record'; // Record interactions to disk
    public const MODE_REPLAY = 'replay'; // Replay interactions from disk
    
    /**
     * Current operation mode
     */
    private string $mode;
    
    /**
     * Storage directory for recordings
     */
    private string $storageDir;
    
    /**
     * Recording middleware instance
     */
    private ?RecordingMiddleware $recordingMiddleware = null;
    
    /**
     * Replay middleware instance
     */
    private ?ReplayMiddleware $replayMiddleware = null;
    
    /**
     * Event dispatcher
     */
    private EventDispatcher $events;
    
    /**
     * Whether to fallback to real requests when in replay mode and no recording is found
     */
    private bool $fallbackToRealRequests;
    
    /**
     * Constructor
     * 
     * @param string $mode The operation mode (pass, record, replay)
     * @param string|null $storageDir Directory to store recordings
     * @param bool $fallbackToRealRequests Whether to fallback to real requests in replay mode
     * @param EventDispatcher|null $events Optional event dispatcher
     */
    public function __construct(
        string $mode = self::MODE_PASS,
        ?string $storageDir = null,
        bool $fallbackToRealRequests = true,
        ?EventDispatcher $events = null
    ) {
        if (!in_array($mode, [self::MODE_PASS, self::MODE_RECORD, self::MODE_REPLAY])) {
            throw new InvalidArgumentException("Invalid mode: $mode");
        }
        
        $this->mode = $mode;
        $this->storageDir = $storageDir ?? (sys_get_temp_dir() . '/http_recordings');
        $this->fallbackToRealRequests = $fallbackToRealRequests;
        $this->events = $events ?? new EventDispatcher();
    }
    
    /**
     * Handle an HTTP request through the middleware
     * 
     * @param HttpClientRequest $request The request to handle
     * @param CanHandleHttp $next The next handler in the chain
     * @return HttpClientResponse The response
     */
    public function handle(HttpClientRequest $request, CanHandleHttp $next): HttpClientResponse
    {
        switch ($this->mode) {
            case self::MODE_PASS:
                return $next->handle($request);
                
            case self::MODE_RECORD:
                if (!$this->recordingMiddleware) {
                    $this->recordingMiddleware = new RecordingMiddleware($this->storageDir, $this->events);
                }
                return $this->recordingMiddleware->handle($request, $next);
                
            case self::MODE_REPLAY:
                if (!$this->replayMiddleware) {
                    $this->replayMiddleware = new ReplayMiddleware(
                        $this->storageDir, 
                        $this->fallbackToRealRequests,
                        $this->events
                    );
                }
                return $this->replayMiddleware->handle($request, $next);
                
            default:
                throw new RuntimeException("Invalid mode: {$this->mode}");
        }
    }
    
    /**
     * Change the current operation mode
     * 
     * @param string $mode New mode
     * @return self
     */
    public function setMode(string $mode): self
    {
        if (!in_array($mode, [self::MODE_PASS, self::MODE_RECORD, self::MODE_REPLAY])) {
            throw new InvalidArgumentException("Invalid mode: $mode");
        }
        
        $this->mode = $mode;
        return $this;
    }
    
    /**
     * Get the current operation mode
     * 
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }
    
    /**
     * Set the storage directory for recordings
     * 
     * @param string $dir Directory path
     * @return self
     */
    public function setStorageDir(string $dir): self
    {
        $this->storageDir = $dir;
        
        if ($this->recordingMiddleware) {
            $this->recordingMiddleware->setStorageDir($dir);
        }
        
        if ($this->replayMiddleware) {
            $this->replayMiddleware->setStorageDir($dir);
        }
        
        return $this;
    }
    
    /**
     * Set whether to fallback to real requests in replay mode
     * 
     * @param bool $fallback Whether to fallback
     * @return self
     */
    public function setFallbackToRealRequests(bool $fallback): self
    {
        $this->fallbackToRealRequests = $fallback;
        
        if ($this->replayMiddleware) {
            $this->replayMiddleware->setFallbackToRealRequests($fallback);
        }
        
        return $this;
    }
    
    /**
     * Get the records repository
     * 
     * @return RequestRecords|null
     */
    public function getRecords(): ?RequestRecords
    {
        if ($this->mode === self::MODE_RECORD && $this->recordingMiddleware) {
            return $this->recordingMiddleware->getRecords();
        }
        
        if ($this->mode === self::MODE_REPLAY && $this->replayMiddleware) {
            return $this->replayMiddleware->getRecords();
        }
        
        // Create records instance on demand if none exists yet
        return new RequestRecords($this->storageDir);
    }
}
