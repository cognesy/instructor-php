<?php

namespace Cognesy\Polyglot\LLM\Drivers\Anthropic;

use Cognesy\Polyglot\LLM\Contracts\CanMapMessages;
use Cognesy\Polyglot\LLM\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\InferenceRequest;
use Cognesy\Utils\Messages\Messages;

class AnthropicBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
        protected bool $parallelToolCalls = false
    ) {}

    public function toRequestBody(InferenceRequest $request): array {
        $options = array_merge($this->config->options, $request->options());

        $this->parallelToolCalls = $options['parallel_tool_calls'] ?? false;
        unset($options['parallel_tool_calls']);

        $requestData = array_merge(array_filter([
            'model' => $request->model() ?: $this->config->model,
            'max_tokens' => $options['max_tokens'] ?? $this->config->maxTokens,
            'system' => Messages::fromArray($request->messages())
                ->forRoles(['system'])
                ->toString(),
            'messages' => $this->messageFormat->map(
                Messages::fromArray($request->messages())
                    ->exceptRoles(['system'])
                    ->toArray()
            ),
        ]), $options);

        // Anthropic does not support response_format or JSON/JSON Schema mode
        unset($requestData['response_format']);

        if ($request->hasTools()) {
            $requestData['tools'] = $this->toTools($request);
            $requestData['tool_choice'] = $this->toToolChoice($request);
        }

        return array_filter($requestData, fn($value) => $value !== null && $value !== [] && $value !== '');
    }

    // INTERNAL /////////////////////////////////////////////

    private function toTools(InferenceRequest $request) : array {
        $tools = $request->tools();
        $result = [];
        foreach ($tools as $tool) {
            $result[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'input_schema' => $tool['function']['parameters'],
            ];
        }
        return $result;
    }

    private function toToolChoice(InferenceRequest $request) : array {
        $toolChoice = $request->toolChoice();
        $tools = $request->tools();

        return match(true) {
            empty($tools) => [],
            empty($toolChoice) => [
                'type' => 'auto',
                'disable_parallel_tool_use' => !$this->parallelToolCalls,
            ],
            is_array($toolChoice) => [
                'type' => 'tool',
                'name' => $toolChoice['function']['name'],
                'disable_parallel_tool_use' => !$this->parallelToolCalls,
            ],
            default => [
                'type' => $this->mapToolChoice($toolChoice),
                'disable_parallel_tool_use' => !$this->parallelToolCalls,
            ],
        };
    }

    protected function mapToolChoice(string $choice) : string {
        return match($choice) {
            'auto' => 'auto',
            'required' => 'any',
            default => 'auto',
        };
    }
}