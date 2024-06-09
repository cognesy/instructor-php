<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Events\ApiClient\ApiRequestErrorRaised;
use Cognesy\Instructor\Events\ApiClient\ApiStreamConnected;
use Cognesy\Instructor\Events\ApiClient\ApiStreamRequestInitiated;
use Cognesy\Instructor\Events\ApiClient\ApiStreamResponseReceived;
use Cognesy\Instructor\Events\ApiClient\ApiStreamUpdateReceived;
use Exception;
use Generator;
use Saloon\Exceptions\Request\RequestException;

trait HandlesStreamApiResponse
{
    /**
     * @return Generator<PartialApiResponse>
     */
    public function stream() : Generator {
        $stream = $this->streamRaw();
        foreach ($stream as $response) {
            if (empty($response) || $this->isDone($response)) {
                continue;
            }
            yield $this->apiRequest->toPartialApiResponse($response);
        }
    }

    protected function streamRaw(): Generator {
        if (!$this->isStreamedRequest()) {
            throw new Exception('You need to use respond() when option stream is set to false');
        }
        $request = $this->getApiRequest();
        $this->withStreaming(true);
        $stream = $this->getStream($request);
        foreach($stream as $response) {
            if (empty($response) || $this->isDone($response)) {
                continue;
            }
            yield $response;
        }
    }

    protected function getStream(ApiRequest $request): Generator {
        $this?->events->dispatch(new ApiStreamRequestInitiated($request));
        try {
            $response = $this->connector($request->requestConfig()->debugConfig)->send($request);
        } catch (RequestException $exception) {
            $this?->events->dispatch(new ApiRequestErrorRaised($exception));
            throw $exception;
        }
        $this?->events->dispatch(new ApiStreamConnected($response));

        $iterator = $this->getStreamIterator(
            stream: $response->stream(),
            getData: $this->getData(...),
            isDone: $this->isDone(...),
        );
        foreach ($iterator as $streamedData) {
            if (empty($streamedData)) {
                continue;
            }
            $this?->events->dispatch(new ApiStreamUpdateReceived($streamedData));
            yield $streamedData;
        }
        $this?->events->dispatch(new ApiStreamResponseReceived($response));
    }

    abstract protected function isDone(string $data): bool;
    abstract protected function getData(string $data): string;
}