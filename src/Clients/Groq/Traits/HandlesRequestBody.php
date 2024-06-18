<?php
namespace Cognesy\Instructor\Clients\Groq\Traits;

use Cognesy\Instructor\ApiClient\Enums\ClientType;

// API REQUEST - GROQ

trait HandlesRequestBody
{
    protected function model() : string {
        return $this->model;
    }

    public function messages(): array {
        if ($this->noScript()) {
            return $this->messages;
        }

        $this->script->section('pre-input')->appendMessage([
            'role' => 'user',
            'content' => "### INPUT DATA\n\n",
        ]);

        $this->script->section('pre-prompt')->appendMessage([
            'role' => 'user',
            'content' => "\n\n### YOUR TASK\n\n",
        ]);

        if ($this->script->section('examples')->notEmpty()) {
            $this->script->section('pre-examples')->appendMessage([
                'role' => 'user',
                'content' => "\n\n### EXAMPLES\n\n",
            ]);
        }

        if ($this->script->section('retries')->notEmpty()) {
            $this->script->section('pre-retries')->appendMessage([
                'role' => 'user',
                'content' => "\n\n### FEEDBACK\n\n",
            ]);
        }

        $this->script->section('pre-response')->appendMessage([
            'role' => 'user',
            'content' => "\n\n### ASSISTANT RESPONSE\n\n",
        ]);

        return $this->script
            ->withContext($this->scriptContext)
            ->select(['pre-prompt', 'prompt', 'pre-examples', 'examples', 'pre-input', 'messages', 'input', 'retries', 'pre-response'])
            ->toSingleSection('merged')
            ->toAlternatingRoles()
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