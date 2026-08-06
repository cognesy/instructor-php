<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Middleware\RecordReplay;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionRecorded;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionSummary;
use Cognesy\Http\Extras\Support\RecordReplay\RequestRecords;
use Cognesy\Http\Extras\Support\RecordReplay\RecordingStream;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Records HTTP interactions for later replay.
 *
 * @deprecated Use RecordReplayMiddleware::recordTo() or recordWith().
 */
class RecordingMiddleware implements HttpMiddleware
{
    private RequestRecords $records;

    private EventDispatcherInterface $events;

    public function __construct(
        string $storageDir,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->records = new RequestRecords($storageDir);
        $this->events = $events ?? new EventDispatcher();
    }

    #[\Override]
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        $response = $next->handle($request);
        if (!$response->isStreamed()) {
            $this->records->save($request, $response);
            $this->events->dispatch(new HttpInteractionRecorded(
                HttpInteractionSummary::fromRequest($request, 'recorded', $response->statusCode()),
            ));
            return $response;
        }

        $recordingResponse = HttpResponse::streaming(
            statusCode: $response->statusCode(),
            headers: $response->headers(),
            stream: new RecordingStream(
                source: $response->rawStream(),
                onCompleted: function (iterable $chunks) use ($request, $response): void {
                    $this->records->saveStreamed($request, $response, $chunks);
                    $this->events->dispatch(new HttpInteractionRecorded(
                        HttpInteractionSummary::fromRequest($request, 'recorded', $response->statusCode()),
                    ));
                },
            ),
        );

        return $recordingResponse;
    }

    /** @deprecated Use the cassette store API; record fixtures are implementation details. */
    public function getRecords(): RequestRecords {
        return $this->records;
    }

    /** @deprecated Configure the directory through RecordReplayMiddleware::recordTo(). */
    public function setStorageDir(string $dir): self {
        $this->records->setStorageDir($dir);
        return $this;
    }
}
