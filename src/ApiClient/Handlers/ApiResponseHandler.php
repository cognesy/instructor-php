<?php

namespace Cognesy\Instructor\ApiClient\Handlers;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Data\Requests\ApiRequest;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\ApiClient\ApiRequestErrorRaised;
use Cognesy\Instructor\Events\ApiClient\ApiRequestInitiated;
use Cognesy\Instructor\Events\ApiClient\ApiResponseReceived;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

class ApiResponseHandler
{
    public function __construct(
        protected ApiConnector $connector,
        protected EventDispatcher $events,
        protected bool $debug = false,
    ) {}

    public function respondRaw(ApiRequest $request): Response {
        $this?->events->dispatch(new ApiRequestInitiated($request));
        try {
            if ($this->debug) {
                $this->connector->debug();
            }
            $response = $this->connector->send($request);
        } catch (RequestException $exception) {
            $this?->events->dispatch(new ApiRequestErrorRaised($exception));
            throw $exception;
        }
        $this?->events->dispatch(new ApiResponseReceived($response));
        return $response;
    }
}
