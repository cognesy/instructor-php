<?php

namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Data\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Handlers\ApiAsyncHandler;
use Cognesy\Instructor\ApiClient\Handlers\ApiResponseHandler;
use Cognesy\Instructor\ApiClient\Handlers\ApiStreamHandler;
use Cognesy\Instructor\Events\EventDispatcher;
use Exception;
use Generator;
use GuzzleHttp\Promise\PromiseInterface;
use Saloon\Http\Response;

abstract class ApiClient implements CanCallApi
{
    public string $defaultModel = '';
    protected EventDispatcher $events;
    protected ApiConnector $connector;
    protected ApiRequest $request;
    /** @var class-string */
    protected string $responseClass;

    public function __construct(
        EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
    }

    /// PUBLIC API - INIT //////////////////////////////////////////////////////////////////////

    public function withEventDispatcher(EventDispatcher $events): self {
        $this->events = $events;
        return $this;
    }

    public function withRequest(ApiRequest $request) : static {
        $this->request = $request;
        return $this;
    }

    public function withPayload(array $payload) : static {
        $this->request = $this->makeRequest($payload);
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

    public function getDefaultModel() : string {
        return $this->defaultModel;
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

    public function respond() : ApiResponse {
        $response = $this->respondRaw();
        return ($this->responseClass)::fromResponse($response);
    }

    public function stream() : Generator {
        $stream = $this->streamRaw();
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

    abstract protected function makeRequest(array $payload): ApiRequest;

    abstract protected function isDone(string $data): bool;

    abstract protected function getData(string $data): string;
}