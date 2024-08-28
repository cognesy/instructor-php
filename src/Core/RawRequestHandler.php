<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Contracts\CanHandleRequest;
use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\Request\RequestSentToLLM;
use Cognesy\Instructor\Events\Request\RequestToLLMFailed;
use Cognesy\Instructor\Events\Request\ResponseReceivedFromLLM;
use Exception;

class RawRequestHandler implements CanHandleRequest
{
    public function __construct(
        private EventDispatcher $events,
    ) {}

    /**
     * Generates a response model via LLM based on provided string or OpenAI style message array
     */
    public function respondTo(Request $request) : string {
        $apiResponse = $this->getApiResponse($request);
        $this->events->dispatch(new ResponseReceivedFromLLM($apiResponse));
        return $apiResponse->content;
    }


    // INTERNAL ////////////////////////////////////////////////////////


    protected function getApiResponse(Request $request) : ApiResponse {
        /** @var CanCallApi $apiClient */
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
