<?php
namespace Cognesy\Instructor\Clients\Gemini\Traits;

trait HandlesRequestBody
{
    protected function system() : array {
        if ($this->noScript()) {
            return match(true) {
                empty($this->system) => [],
                default => ["parts" => [["text" => $this->system]]]
            };
        }

        $text = $this->script
            ->withContext($this->scriptContext)
            ->select(['system'])
            ->toString();

        return match(true) {
            empty($text) => [],
            default => ["parts" => [["text" => $text]]]
        };
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

        $text = $this->script
            ->withContext($this->scriptContext)
            ->select(['prompt', 'pre-examples', 'examples', 'pre-input', 'messages', 'input', 'retries'])
            ->toString();

        return [['role' => 'user', "parts" => [["text" => $text]]]];
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
