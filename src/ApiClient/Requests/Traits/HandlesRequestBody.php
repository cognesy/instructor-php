<?php
namespace Cognesy\Instructor\ApiClient\Requests\Traits;

// API REQUEST - DEFAULT IMPLEMENTATION

use Cognesy\Instructor\ApiClient\Enums\ClientType;

trait HandlesRequestBody
{
    protected function model() : string {
        return $this->model;
    }

    public function messages(): array {
        if ($this->noScript()) {
            return $this->messages;
        }

        return $this
            ->withMetaSections($this->script)
            ->withContext($this->scriptContext)
            ->select([
                'system',
                'pre-input', 'messages', 'input', 'post-input',
                'pre-prompt', 'prompt', 'post-prompt',
                'pre-examples', 'examples', 'post-examples',
                'pre-retries', 'retries', 'post-retries'
            ])
            ->toNativeArray(ClientType::fromRequestClass($this), mergePerRole: true);
    }

    public function tools(): array {
        return $this->tools;
    }

    public function getToolChoice(): string|array {
        if (empty($this->tools)) {
            return '';
        }
        return $this->toolChoice ?: 'auto';
    }

    protected function getResponseSchema() : array {
        return $this->responseFormat['schema'] ?? [];
    }

    protected function getResponseFormat(): array {
        return $this->responseFormat['format'] ?? [];
    }

    public function isStreamed(): bool {
        return $this->requestBody['stream'] ?? false;
    }
}