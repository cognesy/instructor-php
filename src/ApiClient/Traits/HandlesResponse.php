<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Data\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Events\ApiClient\ApiRequestErrorRaised;
use Cognesy\Instructor\Events\ApiClient\ApiRequestInitiated;
use Cognesy\Instructor\Events\ApiClient\ApiResponseReceived;
use Cognesy\Instructor\Traits\HandlesDebug;
use Exception;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

trait HandlesResponse
{
    use HandlesDebug;
    use HandlesApiConnector;
    use HandlesResponseClass;

    public function respond(ApiRequest $request) : ApiResponse {
        return $this->withRequest($request)->get();
    }

    public function get() : ApiResponse {
        if ($this->isStreamedRequest()) {
            throw new Exception('You need to use stream() when option stream is set to true');
        }
        if ($this->debug()) {
            $this->connector->debug();
        }

        $request = $this->getRequest();
        $response = $this->respondRaw($request);
        return $this->makeResponse($response);
    }

    protected function respondRaw(ApiRequest $request): Response {
        $this?->events->dispatch(new ApiRequestInitiated($request));
        try {
            $response = $this->connector->send($request);
        } catch (RequestException $exception) {
            $this?->events->dispatch(new ApiRequestErrorRaised($exception));
            throw $exception;
        }
        $this?->events->dispatch(new ApiResponseReceived($response));
        return $response;
    }
}