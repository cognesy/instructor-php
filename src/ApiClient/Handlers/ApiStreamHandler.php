<?php

namespace Cognesy\Instructor\ApiClient\Handlers;

use Closure;
use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Data\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Mixins\HandlesStreamedResponses;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\ApiClient\ApiRequestErrorRaised;
use Cognesy\Instructor\Events\ApiClient\ApiStreamRequestInitiated;
use Cognesy\Instructor\Events\ApiClient\ApiStreamResponseReceived;
use Cognesy\Instructor\Events\ApiClient\ApiStreamUpdateReceived;
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
        protected bool $debug = false,
    ) {}

    public function streamRaw(ApiRequest $request): Generator {
        $isStreamed = $request->isStreamed();
        if (!$isStreamed) {
            throw new Exception('Streaming is not enabled: set "stream" = true in the request options.');
        }

        $this?->events->dispatch(new ApiStreamRequestInitiated($request));
        try {
            if ($this->debug) {
                $this->connector->debug();
            }
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

//    public function streamAllRaw(ApiRequest $request) : array {
//        $responses = [];
//        $stream = $this->streamRaw($request);
//        foreach ($stream as $response) {
//            $responses[] = $response;
//        }
//        return $responses;
//    }
}