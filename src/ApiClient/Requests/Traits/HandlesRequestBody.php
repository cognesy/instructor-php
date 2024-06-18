<?php
namespace Cognesy\Instructor\ApiClient\Requests\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;

// API REQUEST - DEFAULT IMPLEMENTATION

trait HandlesRequestBody
{
    protected function model() : string {
        return $this->model;
    }

    public function messages(): array {
        if ($this->noScript()) {
            return $this->messages;
        }

        return $this->script
            ->withContext($this->scriptContext)
            ->select(['pre-input', 'messages', 'input', 'prompt', 'pre-examples', 'examples', 'retries'])
            ->toNativeArray(type: ClientType::fromRequestClass(static::class), context: [], mergePerRole: true);
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