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

        if ($this->script->section('examples')->notEmpty()) {
            $this->script->section('pre-examples')->appendMessage([
                'role' => 'assistant',
                'content' => 'Provide examples.',
            ]);
        }

        $this->script->section('pre-input')->appendMessage([
            'role' => 'assistant',
            'content' => "Provide input.",
        ]);

        return $this->script
            ->withContext($this->scriptContext)
            ->select(['prompt', 'pre-examples', 'examples', 'pre-input', 'messages', 'input', 'retries'])
            ->toNativeArray(ClientType::fromRequestClass(static::class));
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