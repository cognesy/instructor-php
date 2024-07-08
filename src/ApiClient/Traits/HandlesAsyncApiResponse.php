<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Events\ApiClient\ApiAsyncRequestInitiated;
use Cognesy\Instructor\Events\ApiClient\ApiAsyncResponseReceived;
use Cognesy\Instructor\Events\ApiClient\ApiRequestErrorRaised;
use Exception;
use GuzzleHttp\Promise\PromiseInterface;
use JetBrains\PhpStorm\Deprecated;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;

#[Deprecated('This is not yet implemented')]
trait HandlesAsyncApiResponse
{
    use HandlesApiRequest;
    use HandlesApiConnector;

    public function async() : PromiseInterface {
        if ($this->isStreamedRequest()) {
            throw new Exception('Async does not support streaming');
        }

        $request = $this->getApiRequest();
        return $this->asyncRaw(
            request: $request,
            onSuccess: fn(Response $response) => $this->apiRequest->toApiResponse($response),
            onError: fn(Exception $exception) => throw $exception
        );
    }

    protected function asyncRaw(ApiRequest $request, callable $onSuccess = null, callable $onError = null) : PromiseInterface {
        $this?->events->dispatch(new ApiAsyncRequestInitiated($request));
        $promise = $this->connector()->sendAsync($request);
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