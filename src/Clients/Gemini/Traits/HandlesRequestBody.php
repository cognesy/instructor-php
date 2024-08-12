<?php
namespace Cognesy\Instructor\Clients\Gemini\Traits;

// GEMINI API

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Arrays;

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

    protected function getResponseFormat(): array {
        return match($this->mode) {
            Mode::MdJson => ["text/plain"],
            default => ["application/json"],
        };
    }

    protected function getResponseSchema() : array {
        return Arrays::removeRecursively($this->jsonSchema, [
            'title',
            'x-php-class',
            'additionalProperties',
        ]);
    }

    private function options() : array {
        return array_filter([
//            "stopSequences" => $this->requestBody['stopSequences'] ?? [],
            "responseMimeType" => $this->getResponseFormat()[0],
            "responseSchema" => match($this->mode) {
                Mode::MdJson => '',
                Mode::Json => $this->getResponseSchema(),
                default => '',
            },
            "candidateCount" => 1,
            "maxOutputTokens" => $this->maxTokens,
            "temperature" => $this->requestBody['temperature'] ?? 1.0,
//            "topP" => "float",
//            "topK" => "integer"
        ]);
    }
}
