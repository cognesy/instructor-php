<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\Contracts\CanCallLLM;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Contracts\CanHandleStreamRequest;
use Cognesy\Instructor\Contracts\CanHandleSyncRequest;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Events\Request\RequestToLLMFailed;
use Cognesy\Instructor\Events\Request\ResponseReceivedFromLLM;
use Exception;
use Generator;

class RawRequestHandler implements CanHandleStreamRequest, CanHandleSyncRequest
{
    public function __construct(
        private EventDispatcher $events,
    ) {}

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function responseFor(Request $request) : string {
        $apiResponse = $this->getApiResponse($request);
        $this->events->dispatch(new ResponseReceivedFromLLM($apiResponse));
        return $apiResponse->content;
    }

    /**
     * Returns response object or generator wrapped in Result monad
     * @return Generator<string>
     */
    public function streamResponseFor(Request $request) : Generator {
        yield from $this->getStreamedResponses($request);
    }

    // INTERNAL ////////////////////////////////////////////////////////

    /**
     * @return Generator<string>
     */
    protected function getStreamedResponses(Request $request) : Generator {
        /** @var CanCallLLM $apiClient */
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

    protected function getApiResponse(Request $request) : ApiResponse {
        /** @var CanCallLLM $apiClient */
        $apiClient = $request->client();
        if ($apiClient === null) {
            throw new Exception("Request does not have an API client");
        }
        $apiRequest = $request->toApiRequest();
        try {
            $this->events->dispatch(new RequestSentToLLM($apiRequest));
            $apiResponse = $apiClient->withApiRequest($apiRequest)->get();
        } catch (Exception $e) {
            $this->events->dispatch(new RequestToLLMFailed($apiClient->getApiRequest(), $e->getMessage()));
            throw $e;
        }
        return $apiResponse;
    }

}
