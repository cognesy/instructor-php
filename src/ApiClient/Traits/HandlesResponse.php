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

trait HandlesResponse
{
    use HandlesApiConnector;
    use HandlesRequest;

    public function respond(ApiRequest $request) : ApiResponse {
        return $this->withApiRequest($request)->get();
    }

    public function get() : ApiResponse {
        if ($this->isStreamedRequest()) {
            throw new Exception('You need to use stream() when option stream is set to true');
        }
        $request = $this->getApiRequest();
        $response = $this->respondRaw($request);
        return $this->request->toApiResponse($response);

    }

    protected function respondRaw(ApiRequest $request): Response {
        $this->events->dispatch(new ApiRequestInitiated($request));
        try {
            $response = $this->connector($request->isDebug())->send($request);
        } catch (RequestException $exception) {
            $this->events->dispatch(new ApiRequestErrorRaised($exception));
            throw $exception;
        }
        $this->events->dispatch(new ApiResponseReceived($response));
        return $response;
    }
}