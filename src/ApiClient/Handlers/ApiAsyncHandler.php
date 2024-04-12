<?php

namespace Cognesy\Instructor\ApiClient\Handlers;

use Cognesy\Instructor\ApiClient\ApiConnector;
use Cognesy\Instructor\ApiClient\Data\Requests\ApiRequest;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\ApiClient\ApiAsyncRequestInitiated;
use Cognesy\Instructor\Events\ApiClient\ApiAsyncResponseReceived;
use Cognesy\Instructor\Events\ApiClient\ApiRequestErrorRaised;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

class ApiAsyncHandler
{
    public function __construct(
        protected ApiConnector $connector,
        protected EventDispatcher $events,
        protected bool $debug = false,
    ) {}

    public function asyncRaw(ApiRequest $request, callable $onSuccess = null, callable $onError = null) : PromiseInterface {
        if ($request->isStreamed()) {
            throw new Exception('Async does not support streaming');
        }
        $this?->events->dispatch(new ApiAsyncRequestInitiated($request));
        if ($this->debug) {
            $this->connector->debug();
        }
        $promise = $this->connector->sendAsync($request);
        if (!empty($onSuccess)) {
            $promise->then(function (Response $response) use ($onSuccess) {
                $this?->events->dispatch(new ApiAsyncResponseReceived($response));
                $onSuccess($response);
            });
        }
        if (!empty($onError)) {
            $promise->otherwise(function (RequestException $exception) use ($onError) {
                $this?->events->dispatch(new ApiRequestErrorRaised($exception));
                $onError($exception);
            });
        }
        return $promise;
    }
}