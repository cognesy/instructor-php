<?php

namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\HttpClient\ApiAsyncRequestInitiated;
use Cognesy\Instructor\Events\HttpClient\ApiAsyncResponseReceived;
use Cognesy\Instructor\Events\HttpClient\ApiRequestErrorRaised;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

class ApiAsyncHandler
{
    public function __construct(
        protected ApiConnector $connector,
        protected EventDispatcher $events,
    ) {}

    public function asyncRaw(JsonRequest $request, callable $onSuccess, callable $onError) : PromiseInterface {
        if ($request->isStreamed()) {
            throw new Exception('Async does not support streaming');
        }

        $this?->events->dispatch(new ApiAsyncRequestInitiated($request));
        $promise = $this->connector->sendAsync($request);
        $promise
            ->then(function (Response $response) use ($onSuccess) {
                $this?->events->dispatch(new ApiAsyncResponseReceived($response));
                $onSuccess($response);
            })
            ->otherwise(function (RequestException $exception) use ($onError) {
                $this?->events->dispatch(new ApiRequestErrorRaised($exception));
                $onError($exception);
            });
        return $promise;
    }
}