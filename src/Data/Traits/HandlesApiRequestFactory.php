<?php

namespace Cognesy\Instructor\Data\Traits;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Request;
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
            messages: $this->makeInstructions(),
            tools: [$this->toolCallSchema()],
            toolChoice: [
                'type' => 'function',
                'function' => ['name' => $this->toolName()]
            ],
            responseFormat: [
                'type' => 'json_object',
                'schema' => $this->jsonSchema()
            ],
            model: $this->modelName(),
            options: $this->options(),
        );
    }

    protected function makeChatCompletionRequest(string $requestClass) : ApiRequest {
        return $this->apiRequestFactory->makeChatCompletionRequest(
            requestClass: $requestClass,
            messages: $this->makeInstructions(),
            model: $this->modelName(),
            options: $this->options(),
        );
    }

    protected function makeJsonCompletionRequest(string $requestClass) : ApiRequest {
        return $this->apiRequestFactory->makeJsonCompletionRequest(
            requestClass: $requestClass,
            messages: $this->makeInstructions(),
            responseFormat: [
                'type' => 'json_object',
                'schema' => $this->jsonSchema()
            ],
            model: $this->modelName(),
            options: $this->options(),
        );
    }

    protected function makeToolsCallRequest(string $requestClass) : ApiRequest {
        return $this->apiRequestFactory->makeToolsCallRequest(
            requestClass: $requestClass,
            messages: $this->makeInstructions(),
            tools: [$this->toolCallSchema()],
            toolChoice: [
                'type' => 'function',
                'function' => ['name' => $this->toolName()]
            ],
            model: $this->modelName(),
            options: $this->options(),
        );
    }
}