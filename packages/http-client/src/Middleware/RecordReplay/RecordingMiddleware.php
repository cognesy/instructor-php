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

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        $response = $next->handle($request);
        $this->records->save($request, $response);
        $this->events->dispatch(new HttpInteractionRecorded($request, $response));
        return $response;
    }

    public function getRecords(): RequestRecords {
        return $this->records;
    }

    public function setStorageDir(string $dir): self {
        $this->records->setStorageDir($dir);
        return $this;
    }
}
