<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\RecordReplay;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Middleware\RecordReplay\Events\HttpInteractionRecorded;
use Cognesy\Http\Stream\ArrayStream;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Records HTTP interactions for later replay.
 */
class RecordingMiddleware implements HttpMiddleware
{
    private RequestRecords $records;

    private ?EventDispatcherInterface $events;

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
            if ($this->events !== null) {
                $this->events->dispatch(new HttpInteractionRecorded($request, $response));
            }
            return $response;
        }

        $chunks = [];
        foreach ($response->stream() as $chunk) {
            $chunks[] = $chunk;
        }

        $replayableResponse = HttpResponse::streaming(
            statusCode: $response->statusCode(),
            headers: $response->headers(),
            stream: ArrayStream::from($chunks),
        );

        $this->records->save($request, $replayableResponse);
        if ($this->events !== null) {
            $this->events->dispatch(new HttpInteractionRecorded($request, $replayableResponse));
        }
        return $replayableResponse;
    }

    public function getRecords(): RequestRecords {
        return $this->records;
    }

    public function setStorageDir(string $dir): self {
        $this->records->setStorageDir($dir);
        return $this;
    }
}
