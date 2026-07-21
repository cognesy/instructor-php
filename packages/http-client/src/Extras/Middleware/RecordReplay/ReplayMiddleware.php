<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Middleware\RecordReplay;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionFallback;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionNotFound;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionReplayed;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionSummary;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\RecordingNotFoundException;
use Cognesy\Http\Extras\Support\RecordReplay\RequestRecords;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Replays recorded HTTP interactions.
 *
 * @deprecated Use RecordReplayMiddleware::replayFrom() or replayWith().
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
                $this->events->dispatch(new HttpInteractionReplayed(
                    HttpInteractionSummary::fromRequest($request, 'replayed', $response->statusCode()),
                ));
            }
            return $response;
        }

        if (!$this->fallbackToRealRequests) {
            if ($this->events !== null) {
                $this->events->dispatch(new HttpInteractionNotFound(
                    HttpInteractionSummary::fromRequest($request, 'not_found'),
                ));
            }
            throw new RecordingNotFoundException(
                "No recording found for {$request->method()} request.",
            );
        }
        if ($this->events !== null) {
            $this->events->dispatch(new HttpInteractionFallback(
                HttpInteractionSummary::fromRequest($request, 'fallback'),
            ));
        }
        return $next->handle($request);
    }

    /** @deprecated Use the cassette store API; replay fixtures are implementation details. */
    public function getRecords(): RequestRecords {
        return $this->records;
    }

    /** @deprecated Configure replay misses through RecordReplayPolicy. */
    public function setFallbackToRealRequests(bool $fallback): self {
        $this->fallbackToRealRequests = $fallback;
        return $this;
    }

    /** @deprecated Configure the directory through RecordReplayMiddleware::replayFrom(). */
    public function setStorageDir(string $dir): self {
        $this->records->setStorageDir($dir);
        return $this;
    }
}
