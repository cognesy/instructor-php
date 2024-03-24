<?php

namespace Cognesy\Instructor\HttpClient;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\ApiAsyncRequestInitiated;
use Cognesy\Instructor\Events\HttpClient\ApiAsyncResponseReceived;
use Cognesy\Instructor\Events\HttpClient\ApiRequestErrorRaised;
use Cognesy\Instructor\Events\HttpClient\ApiRequestInitiated;
use Cognesy\Instructor\Events\HttpClient\ApiResponseReceived;
use Cognesy\Instructor\Events\HttpClient\ApiStreamRequestInitiated;
use Cognesy\Instructor\Events\HttpClient\ApiStreamResponseReceived;
use Cognesy\Instructor\Events\HttpClient\ApiStreamUpdateReceived;
use Exception;
use Generator;
use GuzzleHttp\Promise\PromiseInterface;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

abstract class LLMClient
{
    use HandlesStreamedResponses;

    protected EventDispatcher $events;
    protected LLMConnector $connector;
    protected JsonPostRequest $request;

    public function __construct()
    {
        $this->events = new EventDispatcher();
    }

    /// PUBLIC API /////////////////////////////////////////////////////////////////////////////

    public function withEventDispatcher(EventDispatcher $events): self
    {
        $this->events = $events;
        return $this;
    }

    public function withRequest(JsonPostRequest $request) : static {
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

    public function send(): Response {
        $this?->events->dispatch(new ApiRequestInitiated($this->request));
        try {
            $response = $this->connector->send($this->request);
        } catch (RequestException $exception) {
            $this?->events->dispatch(new ApiRequestErrorRaised($exception));
            throw $exception;
        }
        $this?->events->dispatch(new ApiResponseReceived($response));
        return $response;
    }

    public function stream(): Generator {
        $isStreamed = $this->request->isStreamed();
        if (!$isStreamed) {
            throw new Exception('Streaming is not enabled: set "stream" = true in the request options.');
        }

        $this?->events->dispatch(new ApiStreamRequestInitiated($this->request));
        try {
            $response = $this->connector->send($this->request);
        } catch (RequestException $exception) {
            $this?->events->dispatch(new ApiRequestErrorRaised($exception));
            throw $exception;
        }
        $this?->events->dispatch(new ApiStreamResponseReceived($response));

        foreach ($this->getStreamIterator(
            stream: $response->stream(),
            getData: $this->getData(...),
            isDone: $this->isDone(...),
        ) as $streamedData) {
            if (empty($streamedData)) {
                continue;
            }
            $this?->events->dispatch(new ApiStreamUpdateReceived($streamedData));
            yield $streamedData;
        }
    }

    public function streamAll() : array {
        $responses = [];
        foreach ($this->stream() as $response) {
            $responses[] = $response;
        }
        return $responses;
    }

    public function async(callable $onSuccess, callable $onError) : PromiseInterface {
        if ($this->request->isStreamed()) {
            throw new Exception('Async does not support streaming');
        }

        $this?->events->dispatch(new ApiAsyncRequestInitiated($this->request));
        $promise = $this->connector->sendAsync($this->request);
        $promise
            ->then(function (Response $response) use ($onSuccess) {
                $this?->events->dispatch(new ApiAsyncResponseReceived($response));
                $onSuccess($response);
            })
            ->otherwise(function (RequestException $exception) use ($onError) {
                $this?->events->dispatch(new ApiRequestErrorRaised($exception));
                $onError($exception);
            });
        return $promise;
    }

    /// API SPECIFIC IMPLEMENTATIONS ///////////////////////////////////////////////////////////////

    abstract protected function isDone(string $data) : bool;

    abstract protected function getData(string $data) : string;
}