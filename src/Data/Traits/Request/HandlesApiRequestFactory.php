<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;

trait HandlesApiRequestFactory
{
    private ?ApiRequestFactory $apiRequestFactory;

    public function withApiRequestFactory(ApiRequestFactory $apiRequestFactory): static {
        $this->apiRequestFactory = $apiRequestFactory;
        return $this;
    }

    public function apiRequestFactory() : ApiRequestFactory {
        return $this->apiRequestFactory;
    }

    public function toApiRequest() : ApiRequest {
        if (empty($this->model())) {
            $this->withModel($this->client->defaultModel());
        }
        if (empty($this->option('max_tokens'))) {
            $this->setOption('max_tokens', $this->client->defaultMaxTokens);
        }

        $requestClass = $this->client->getModeRequestClass($this->mode());
        return $this->apiRequestFactory->makeRequest(
            requestClass: $requestClass,
            body: $this->toApiRequestBody(),
            endpoint: $this->endpoint(),
            method: $this->method(),
            options: $this->options,
            data: $this->data(),
        );
    }
}