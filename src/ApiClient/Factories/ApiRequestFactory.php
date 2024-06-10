<?php

namespace Cognesy\Instructor\ApiClient\Factories;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Saloon\Enums\Method;

class ApiRequestFactory
{
    public function __construct(
        private ApiRequestConfig $requestConfig,
    ) {}

    /**
     * @param class-string $requestClass
     */
    public function makeRequest(
        string $requestClass,
        array $body,
        string $endpoint = '',
        Method $method = Method::POST,
        array $data = [],
    ): ApiRequest {
        $request = new $requestClass($body, $endpoint, $method, $this->requestConfig, $data);
        $request->config()->add('stream', true);
        return $request;
    }
}
