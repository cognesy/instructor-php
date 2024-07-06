<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\Factories\ApiRequestFactory;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Messages\Script;

trait HandlesApiRequestFactory
{
    private ?ApiRequestFactory $apiRequestFactory;

    public function apiRequestFactory() : ApiRequestFactory {
        return $this->apiRequestFactory;
    }

    public function toApiRequest() : ApiRequest {
        $requestClass = $this->client->getModeRequestClass($this->mode());
        $messages = $this->withMetasections($this->script())
            ->select([
                'system',
                'pre-input', 'messages', 'input', 'post-input',
                'pre-prompt', 'prompt', 'post-prompt',
                'pre-examples', 'examples', 'post-examples',
                'pre-retries', 'retries', 'post-retries'
            ])
            ->toArray(
                context: ['json_schema' => $this->responseModel()?->toJsonSchema() ?? []]
            );
        return $this->apiRequestFactory->makeRequest(
            requestClass: $requestClass,
            body: array_filter(array_merge(
                ['messages' => $messages],
                ['model' => $this->modelName()],
                $this->options(),
            )),
            endpoint: $this->endpoint(),
            method: $this->method(),
            data: $this->data(),
        );
    }

    // INTERNAL ///////////////////////////////////////////////////////////////

    protected function withApiRequestFactory(ApiRequestFactory $apiRequestFactory): static {
        $this->apiRequestFactory = $apiRequestFactory;
        return $this;
    }

    protected function withMetaSections(Script $script) : Script {
        $result = Script::fromScript($script);

        $result->section('pre-input')->appendMessageIfEmpty([
            'role' => 'user',
            'content' => "INPUT:",
        ]);

        $result->section('pre-prompt')->appendMessageIfEmpty([
            'role' => 'user',
            'content' => "TASK:",
        ]);

        if ($result->section('examples')->notEmpty()) {
            $result->section('pre-examples')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "EXAMPLES:",
            ]);
        }

        $result->section('post-examples')->appendMessageIfEmpty([
            'role' => 'user',
            'content' => "RESPONSE:",
        ]);

        if ($result->section('retries')->notEmpty()) {
            $result->section('pre-retries')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "FEEDBACK:",
            ]);
            $result->section('post-retries')->appendMessageIfEmpty([
                'role' => 'user',
                'content' => "CORRECTED RESPONSE:",
            ]);
        }

        return $result;
    }
}