<?php
namespace Cognesy\Instructor\ApiClient\Requests\Traits;

// API REQUEST - DEFAULT IMPLEMENTATION

trait HandlesRequestBody
{
    protected function model() : string {
        return $this->model;
    }

    public function messages(): array {
        return $this->messages;
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
}