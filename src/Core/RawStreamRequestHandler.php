<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\Responses\PartialApiResponse;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Events\Request\RequestToLLMFailed;
use Exception;
use Generator;

class RawStreamRequestHandler implements CanHandleStreamRequest
{
    public function __construct(
        private EventDispatcher $events,
    ) {}

    /**
     * Returns response object or generator wrapped in Result monad
     * @return Generator<string>
     */
    public function respondTo(Request $request) : Generator {
        yield from $this->getStreamedResponses($request);
    }


    // INTERNAL ////////////////////////////////////////////////////////

    /**
     * @return Generator<string>
     */
    protected function getStreamedResponses(Request $request) : Generator {
        /** @var ApiClient $apiClient */
        $apiClient = $request->client();
        if ($apiClient === null) {
            throw new Exception("Request does not have an API client");
        }
        $apiRequest = $request->toApiRequest();
        try {
            $this->events->dispatch(new RequestSentToLLM($apiRequest));
            $stream = $apiClient->withApiRequest($apiRequest)->stream();
            foreach($stream as $partialResponse) {
                $this->events->dispatch(new StreamedResponseReceived($partialResponse));
                yield $partialResponse->delta;
            }
        } catch(Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($apiClient->getApiRequest(), $e->getMessage()));
            throw $e;
        }
    }
}
