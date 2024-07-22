<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Exception;
use Saloon\Http\Response;

trait HandlesApiRequest
{
    protected ApiRequest $apiRequest;

    public function withApiRequest(ApiRequest $request) : static {
        $this->apiRequest = $request;
        return $this;
    }

    public function getApiRequest() : ApiRequest {
        if (empty($this->apiRequest)) {
            throw new Exception('Request is not set');
        }
        if (!empty($this->queryParams)) {
            $this->apiRequest->query()->set($this->queryParams);
        }
        return $this->apiRequest;
    }

    protected function isStreamedRequest() : bool {
        return $this->apiRequest->isStreamed();
    }

    protected function withStreaming(bool $streaming) : void {
        $this->apiRequest->config()->add('stream', $streaming);
    }

    protected function getRequestHeaders(Response $response) : array {
        $headers = [];
        foreach ($response->getPsrRequest()->getHeaders() as $headerName => $value) {
            $headers[$headerName] = implode(';', $value);
        }
        return $headers;
    }

    protected function getResponseHeaders(Response $response) : array {
        $headers = [];
        foreach ($response->getPsrResponse()->getHeaders() as $headerName => $value) {
            $headers[$headerName] = implode(';', $value);
        }
        return $headers;
    }
}