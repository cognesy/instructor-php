<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Enums\Mode;

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
        return $this->fromRequest();
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////////////////////////////

    protected function fromRequest() : ApiRequest {
        $requestClass = $this->client->getModeRequestClass($this->mode());
        return match ($this->mode()) {
            Mode::MdJson => $this->makeChatCompletionRequest($requestClass),
            Mode::Json => $this->makeJsonCompletionRequest($requestClass),
            Mode::Tools => $this->makeToolsCallRequest($requestClass),
            default => $this->makeApiRequest($requestClass),
        };
    }

    protected function makeApiRequest(string $requestClass) : ApiRequest {
        return $this->apiRequestFactory->makeRequest(
            requestClass: $requestClass,
            messages: $this->messages(),
            tools: $this->toolCallSchema(),
            toolChoice: $this->toolChoice(),
            responseFormat: $this->responseFormat(),
            model: $this->modelName(),
            options: $this->makeOptions(),
        );
    }

    protected function makeChatCompletionRequest(string $requestClass) : ApiRequest {
        return $this->apiRequestFactory->makeChatCompletionRequest(
            requestClass: $requestClass,
            messages: $this->messages(),
            model: $this->modelName(),
            options: $this->makeOptions(),
        );
    }

    protected function makeJsonCompletionRequest(string $requestClass) : ApiRequest {
        return $this->apiRequestFactory->makeJsonCompletionRequest(
            requestClass: $requestClass,
            messages: $this->messages(),
            responseFormat: $this->responseFormat(),
            model: $this->modelName(),
            options: $this->makeOptions(),
        );
    }

    protected function makeToolsCallRequest(string $requestClass) : ApiRequest {
        return $this->apiRequestFactory->makeToolsCallRequest(
            requestClass: $requestClass,
            messages: $this->messages(),
            tools: $this->toolCallSchema(),
            toolChoice: $this->toolChoice(),
            model: $this->modelName(),
            options: $this->makeOptions(),
        );
    }
}