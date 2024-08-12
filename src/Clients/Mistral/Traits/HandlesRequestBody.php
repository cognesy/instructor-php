<?php
namespace Cognesy\Instructor\Clients\Mistral\Traits;

trait HandlesRequestBody
{
    protected function model() : string {
        return $this->model;
    }

    public function messages(): array {
        return $this->messages;
    }

    public function tools() : array {
        return $this->tools;
    }

    public function getToolChoice(): string|array {
        return 'any';
    }

    protected function getResponseFormat(): array {
        return ['type' => 'json_object'];
    }

    protected function getResponseSchema(): array {
        return [];
    }
}
