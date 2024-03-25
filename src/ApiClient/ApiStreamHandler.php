<?php

namespace Cognesy\Instructor\ApiClient;

use Closure;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\ApiRequestErrorRaised;
use Cognesy\Instructor\Events\HttpClient\ApiStreamRequestInitiated;
use Cognesy\Instructor\Events\HttpClient\ApiStreamResponseReceived;
use Cognesy\Instructor\Events\HttpClient\ApiStreamUpdateReceived;
use Exception;
use Generator;
use Saloon\Exceptions\Request\RequestException;

class ApiStreamHandler
{
    use HandlesStreamedResponses;

    public function __construct(
        protected ApiConnector $connector,
        protected EventDispatcher $events,
        protected Closure $isDone,
        protected Closure $getData,
    ) {}

    public function streamRaw(JsonRequest $request): Generator {
        $isStreamed = $request->isStreamed();
        if (!$isStreamed) {
            throw new Exception('Streaming is not enabled: set "stream" = true in the request options.');
        }

        $this?->events->dispatch(new ApiStreamRequestInitiated($request));
        try {
            $response = $this->connector->send($request);
        } catch (RequestException $exception) {
            $this?->events->dispatch(new ApiRequestErrorRaised($exception));
            throw $exception;
        }
        $this?->events->dispatch(new ApiStreamResponseReceived($response));

        $iterator = $this->getStreamIterator(
            stream: $response->stream(),
            getData: $this->getData,
            isDone: $this->isDone,
        );
        foreach ($iterator as $streamedData) {
            if (empty($streamedData)) {
                continue;
            }
            $this?->events->dispatch(new ApiStreamUpdateReceived($streamedData));
            yield $streamedData;
        }
    }

    public function streamAllRaw(JsonRequest $request) : array {
        $responses = [];
        $stream = $this->streamRaw($request);
        foreach ($stream as $response) {
            $responses[] = $response;
        }
        return $responses;
    }
}