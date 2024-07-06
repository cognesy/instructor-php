<?php
namespace Cognesy\Instructor\Clients\Anthropic\Traits;

//use Cognesy\Instructor\ApiClient\Enums\ClientType;
//use Cognesy\Instructor\Enums\Mode;

// ANTHROPIC API

trait HandlesRequestBody
{
    protected function model() : string {
        return $this->model;
    }

    public function messages(): array {
        return $this->messages;
    }

    public function tools(): array {
        if (empty($this->tools)) {
            return [];
        }

        $anthropicFormat = [];
        foreach ($this->tools as $tool) {
            $anthropicFormat[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'input_schema' => $tool['function']['parameters'],
            ];
        }

        return $anthropicFormat;
    }

    public function getToolChoice(): string|array {
        return match(true) {
            empty($this->tools) => '',
            is_array($this->toolChoice) => [
                'type' => 'tool',
                'name' => $this->toolChoice['function']['name'],
            ],
            empty($this->toolChoice) => [
                'type' => 'auto',
            ],
            default => [
                'type' => $this->toolChoice,
            ],
        };
    }

    protected function getResponseFormat(): array {
        return [];
    }

    protected function getResponseSchema() : array {
        return $this->responseFormat['schema'] ?? [];
    }
}
