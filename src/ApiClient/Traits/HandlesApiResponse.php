<?php
namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\ApiClient\Utils\Debugger;
use Cognesy\Instructor\Events\ApiClient\ApiRequestErrorRaised;
use Cognesy\Instructor\Events\ApiClient\ApiRequestInitiated;
use Cognesy\Instructor\Events\ApiClient\ApiRequestSent;
use Cognesy\Instructor\Events\ApiClient\ApiResponseReceived;
use Exception;
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
        $apiResponse = $this->apiRequest->toApiResponse($response);

        $this->events->dispatch(new ApiResponseReceived(
            $response->status(),
            $this->getRequestHeaders($response),
            $apiResponse->content,
        ));

        if ($request->requestConfig()->isDebug()) {
            Debugger::requestDebugger($response->getPendingRequest(), $response->getPsrRequest());
            Debugger::responseDebugger($response, $response->getPsrResponse(), $apiResponse->content);
        }

        return $apiResponse;
    }

    protected function respondRaw(ApiRequest $request): Response {
        $this->events->dispatch(new ApiRequestInitiated($request->toArray()));
        try {
            $response = $this->connector()->send($request);

            $this->events->dispatch(new ApiRequestSent(
                uri: (string) $response->getPsrRequest()->getUri(),
                method: $response->getPsrRequest()->getMethod(),
                headers: $this->getRequestHeaders($response),
                body: (string) $response->getPsrRequest()->getBody(),
            ));
        } catch (Exception $exception) {
            $this->events->dispatch(new ApiRequestErrorRaised($exception));
            throw $exception;
        }
        return $response;
    }
}