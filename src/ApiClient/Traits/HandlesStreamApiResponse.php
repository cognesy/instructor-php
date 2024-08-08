<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\ApiClient\Utils\Debugger;
use Cognesy\Instructor\Events\ApiClient\ApiRequestErrorRaised;
use Cognesy\Instructor\Events\ApiClient\ApiStreamConnected;
use Cognesy\Instructor\Events\ApiClient\ApiStreamRequestInitiated;
use Cognesy\Instructor\Events\ApiClient\ApiStreamRequestSent;
use Cognesy\Instructor\Events\ApiClient\ApiStreamResponseReceived;
use Cognesy\Instructor\Events\ApiClient\ApiStreamUpdateReceived;
use Exception;
use Generator;

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

    /**
     * @return Generator<string>
     */
    protected function streamRaw(): Generator {
        if (!$this->isStreamedRequest()) {
            throw new Exception('You need to use respond() when option stream is set to false');
        }
        $request = $this->getApiRequest();
        $this->withStreaming(true);
        $this?->events->dispatch(new ApiStreamRequestInitiated($request->toArray()));

        try {
            $response = $this->connector()->send($request);

            $this->events->dispatch(new ApiStreamRequestSent(
                uri: (string) $response->getPsrRequest()->getUri(),
                method: $response->getPsrRequest()->getMethod(),
                headers: $this->getRequestHeaders($response),
                body: (string) $response->getPsrRequest()->getBody(),
            ));
        } catch (Exception $exception) {
            $this?->events->dispatch(new ApiRequestErrorRaised($exception));
            throw $exception;
        }
        $this?->events->dispatch(new ApiStreamConnected($response->status()));

        $iterator = $this->getStreamIterator(
            stream: $response->stream(),
            getData: $this->getData(...),
            isDone: $this->isDone(...),
        );

        $body = '';
        foreach ($iterator as $streamedData) {
            $body .= $streamedData . PHP_EOL;
            if (empty($streamedData)) {
                continue;
            }
            $this?->events->dispatch(new ApiStreamUpdateReceived($streamedData));
            yield $streamedData;
        }

        $this?->events->dispatch(new ApiStreamResponseReceived(
            $response->status(),
            $this->getResponseHeaders($response),
            $body,
        ));

        if ($request->requestConfig()->isDebug()) {
            Debugger::requestDebugger($response->getPendingRequest(), $response->getPsrRequest());
            Debugger::responseDebugger($response, $response->getPsrResponse(), $body ?? '');
        }
    }

    abstract protected function isDone(string $data): bool;
    abstract protected function getData(string $data): string;
}
