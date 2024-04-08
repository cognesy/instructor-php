<?php
namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Data\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Data\Responses\PartialApiResponse;
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
    protected array $queryParams = [];
    /** @var class-string */
    protected string $responseClass;
    protected bool $debug = false;

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

    public function withQueryParam(string $name, string $value): self {
        $this->queryParams[$name] = $value;
        return $this;
    }

    public function debug(bool $debug = true): self {
        $this->debug = $debug;
        return $this;
    }

    /// PUBLIC API - PROCESSED ////////////////////////////////////////////////////////////////

    public function respond(ApiRequest $request) : ApiResponse {
        return $this->withRequest($request)->get();
    }

    public function get() : ApiResponse {
        return ($this->responseClass)::fromResponse($this->respondRaw());
    }

    /**
     * @return Generator<PartialApiResponse>
     */
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

    public function async() : ?PromiseInterface {
        throw new Exception('Not implemented');
    }

    /// INTERNAL //////////////////////////////////////////////////////////////////////////////

    protected function respondRaw(): Response {
        if ($this->request->isStreamed()) {
            throw new Exception('You need to use stream() when option stream is set to true');
        }
        return (new ApiResponseHandler($this->connector, $this->events, $this->debug))->respondRaw($this->getRequest());
    }

    protected function streamRaw(): Generator {
        if (!$this->request->isStreamed()) {
            throw new Exception('You need to use respond() when option stream is set to false');
        }
        return (new ApiStreamHandler($this->connector, $this->events, $this->isDone(...), $this->getData(...), $this->debug))->streamRaw($this->getRequest());
    }

    protected function asyncRaw(callable $onSuccess, callable $onError) : PromiseInterface {
        return (new ApiAsyncHandler($this->connector, $this->events, $this->debug))->asyncRaw($this->getRequest(), $onSuccess, $onError);
    }

    public function getRequest() : ApiRequest {
        if (empty($this->request)) {
            throw new Exception('Request is not set');
        }
        if (!empty($this->queryParams)) {
            $this->request->query()->set($this->queryParams);
        }
        return $this->request;
    }

    protected function getModel(string $model) : string {
        return $model ?: $this->defaultModel;
    }

    abstract protected function isDone(string $data): bool;

    abstract protected function getData(string $data): string;
}