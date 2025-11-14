<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Middleware\RecordReplay\Events\HttpInteractionFallback;
use Cognesy\Http\Middleware\RecordReplay\Events\HttpInteractionNotFound;
use Cognesy\Http\Middleware\RecordReplay\Events\HttpInteractionReplayed;
use Cognesy\Http\Middleware\RecordReplay\Exceptions\RecordingNotFoundException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Replays recorded HTTP interactions.
 */
class ReplayMiddleware implements HttpMiddleware
{
    private RequestRecords $records;
    private bool $fallbackToRealRequests;
    private ?EventDispatcherInterface $events;

    public function __construct(
        string $storageDir,
        bool $fallbackToRealRequests = true,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->records = new RequestRecords($storageDir);
        $this->fallbackToRealRequests = $fallbackToRealRequests;
        $this->events = $events ?? new EventDispatcher();
    }

    #[\Override]
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        $record = $this->records->find($request);

        if ($record) {
            $response = $record->toResponse($request->isStreamed());
            if ($this->events !== null) {
                $this->events->dispatch(new HttpInteractionReplayed([
                    'method' => $request->method(),
                    'url' => $request->url(),
                    'statusCode' => $response->statusCode(),
                ]));
            }
            return $response;
        }

        if (!$this->fallbackToRealRequests) {
            if ($this->events !== null) {
                $this->events->dispatch(new HttpInteractionNotFound([
                    'method' => $request->method(),
                    'url' => $request->url(),
                ]));
            }
            throw new RecordingNotFoundException(
                "No recording found for request: {$request->method()} {$request->url()}",
            );
        }
        if ($this->events !== null) {
            $this->events->dispatch(new HttpInteractionFallback($request));
        }
        return $next->handle($request);
    }

    public function getRecords(): RequestRecords {
        return $this->records;
    }

    public function setFallbackToRealRequests(bool $fallback): self {
        $this->fallbackToRealRequests = $fallback;
        return $this;
    }

    public function setStorageDir(string $dir): self {
        $this->records->setStorageDir($dir);
        return $this;
    }
}
