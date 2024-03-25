<?php

namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\Events\EventDispatcher;
use Exception;
use Generator;
use GuzzleHttp\Promise\PromiseInterface;
use Saloon\Http\Response;

abstract class ApiClient
{
    protected EventDispatcher $events;
    protected ApiConnector $connector;
    protected JsonRequest $request;
    protected string $responseClass;

    public function __construct()
    {
        $this->events = new EventDispatcher();
    }

    /// PUBLIC API - INIT //////////////////////////////////////////////////////////////////////

    public function withEventDispatcher(EventDispatcher $events): self {
        $this->events = $events;
        return $this;
    }

    public function withRequest(JsonRequest $request) : static {
        $this->request = $request;
        return $this;
    }

    public function onEvent(string $eventClass, callable $callback) : static {
        $this?->events->addListener($eventClass, $callback);
        return $this;
    }

    public function wiretap(callable $callback) : static {
        $this?->events->wiretap($callback);
        return $this;
    }

    /// PUBLIC API - RAW //////////////////////////////////////////////////////////////////////

    public function respondRaw(): Response {
        if ($this->request->isStreamed()) {
            throw new Exception('You need to use stream() when option stream is set to true');
        }
        return (new ApiResponseHandler($this->connector, $this->events))->respondRaw($this->request);
    }

    public function streamRaw(): Generator {
        if (!$this->request->isStreamed()) {
            throw new Exception('You need to use respond() when option stream is set to false');
        }
        return (new ApiStreamHandler($this->connector, $this->events, $this->isDone(...), $this->getData(...)))->streamRaw($this->request);
    }

    public function asyncRaw(callable $onSuccess, callable $onError) : PromiseInterface {
        return (new ApiAsyncHandler($this->connector, $this->events))->asyncRaw($this->request, $onSuccess, $onError);
    }

    /// PUBLIC API - PROCESSED ////////////////////////////////////////////////////////////////

    public function respond() : JsonResponse {
        $response = $this->respondRaw($this->request);
        return ($this->responseClass)::fromResponse($response);
    }

    public function stream() : Generator {
        $stream = $this->streamRaw($this->request);
        foreach ($stream as $response) {
            yield ($this->responseClass)::fromPartialResponse($response);
        }
    }

    public function streamAll() : array {
        $responses = [];
        foreach ($this->stream() as $response) {
            $responses[] = $response;
        }
        return $responses;
    }

    /// INTERNAL //////////////////////////////////////////////////////////////////////////////

    abstract protected function isDone(string $data): bool;

    abstract protected function getData(string $data): string;
}