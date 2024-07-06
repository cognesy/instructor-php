<?php
namespace Cognesy\Instructor\Clients\Gemini\Traits;

// GEMINI API

trait HandlesRequestBody
{
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
        return ['application/json'];
    }

    private function options() : array {
        return array_filter([
//            "stopSequences" => $this->requestBody['stopSequences'] ?? [],
            "responseMimeType" => $this->getResponseFormat()[0],
            "responseSchema" => $this->getResponseSchema(),
            "candidateCount" => 1,
            "maxOutputTokens" => $this->maxTokens,
            "temperature" => $this->requestBody['temperature'] ?? 1.0,
//            "topP" => "float",
//            "topK" => "integer"
        ]);
    }
}
