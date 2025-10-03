<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * HTTP middleware that can record HTTP interactions and replay them.
 * This is a facade that delegates to specialized middleware based on mode.
 */
class RecordReplayMiddleware implements HttpMiddleware
{
    public const MODE_PASS = 'pass';    // Pass through without recording/replaying
    public const MODE_RECORD = 'record'; // Record interactions to disk
    public const MODE_REPLAY = 'replay'; // Replay interactions from disk

    private string $mode;
    private string $storageDir;
    private ?RecordingMiddleware $recordingMiddleware = null;
    private ?ReplayMiddleware $replayMiddleware = null;
    private EventDispatcherInterface $events;
    private bool $fallbackToRealRequests;

    public function __construct(
        string $mode = self::MODE_PASS,
        ?string $storageDir = null,
        bool $fallbackToRealRequests = true,
        ?EventDispatcherInterface $events = null,
    ) {
        if (!in_array($mode, [self::MODE_PASS, self::MODE_RECORD, self::MODE_REPLAY])) {
            throw new InvalidArgumentException("Invalid mode: $mode");
        }

        $this->mode = $mode;
        $this->storageDir = $storageDir ?? (sys_get_temp_dir() . '/http_recordings');
        $this->fallbackToRealRequests = $fallbackToRealRequests;
        $this->events = $events ?? new EventDispatcher();
    }

    #[\Override]
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
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
                        $this->events,
                    );
                }
                return $this->replayMiddleware->handle($request, $next);

            default:
                throw new RuntimeException("Invalid mode: {$this->mode}");
        }
    }

    public function setMode(string $mode): self {
        if (!in_array($mode, [self::MODE_PASS, self::MODE_RECORD, self::MODE_REPLAY])) {
            throw new InvalidArgumentException("Invalid mode: $mode");
        }

        $this->mode = $mode;
        return $this;
    }

    public function getMode(): string {
        return $this->mode;
    }

    public function setStorageDir(string $dir): self {
        $this->storageDir = $dir;

        if ($this->recordingMiddleware) {
            $this->recordingMiddleware->setStorageDir($dir);
        }

        if ($this->replayMiddleware) {
            $this->replayMiddleware->setStorageDir($dir);
        }

        return $this;
    }

    public function setFallbackToRealRequests(bool $fallback): self {
        $this->fallbackToRealRequests = $fallback;

        if ($this->replayMiddleware) {
            $this->replayMiddleware->setFallbackToRealRequests($fallback);
        }

        return $this;
    }

    public function getRecords(): ?RequestRecords {
        if ($this->mode === self::MODE_RECORD && $this->recordingMiddleware !== null) {
            return $this->recordingMiddleware->getRecords();
        }

        if ($this->mode === self::MODE_REPLAY && $this->replayMiddleware !== null) {
            return $this->replayMiddleware->getRecords();
        }
        return new RequestRecords($this->storageDir);
    }
}
