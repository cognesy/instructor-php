<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Events\ApiClient\ApiRequestErrorRaised;
use Cognesy\Instructor\Events\ApiClient\ApiRequestInitiated;
use Cognesy\Instructor\Events\ApiClient\ApiResponseReceived;
use Exception;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

trait HandlesApiResponse
{
    public function respond(ApiRequest $request) : ApiResponse {
        return $this->withApiRequest($request)->get();
    }

    public function get() : ApiResponse {
        if ($this->isStreamedRequest()) {
            throw new Exception('Use stream() to get response when option stream is set to true');
        }
        $request = $this->getApiRequest();
        $response = $this->respondRaw($request);
        return $this->apiRequest->toApiResponse($response);
    }

    protected function respondRaw(ApiRequest $request): Response {
        $this->events->dispatch(new ApiRequestInitiated($request->toArray()));
        try {
            $response = $this->connector($request->requestConfig()->debugConfig)->send($request);
        } catch (RequestException $exception) {
            $this->events->dispatch(new ApiRequestErrorRaised($exception));
            throw $exception;
        }
        $this->events->dispatch(new ApiResponseReceived($response->status()));
        return $response;
    }
}