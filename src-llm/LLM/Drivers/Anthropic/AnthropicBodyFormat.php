<?php

namespace Cognesy\LLM\LLM\Drivers\Anthropic;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\LLM\LLM\Contracts\CanMapMessages;
use Cognesy\LLM\LLM\Contracts\CanMapRequestBody;
use Cognesy\LLM\LLM\Data\LLMConfig;
use Cognesy\Utils\Messages\Messages;

class AnthropicBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
        protected bool $parallelToolCalls = false
    ) {}

    public function map(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        Mode $mode
    ): array {
        $this->parallelToolCalls = $options['parallel_tool_calls'] ?? false;
        unset($options['parallel_tool_calls']);

        $request = array_merge(array_filter([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $options['max_tokens'] ?? $this->config->maxTokens,
            'system' => Messages::fromArray($messages)
                ->forRoles(['system'])
                ->toString(),
            'messages' => $this->messageFormat->map(
                Messages::fromArray($messages)
                    ->exceptRoles(['system'])
                    ->toArray()
            ),
        ]), $options);

        if (!empty($tools)) {
            $request['tools'] = $this->toTools($tools);
            $request['tool_choice'] = $this->toToolChoice($toolChoice, $tools);
        }

        return $request;
    }

    // INTERNAL /////////////////////////////////////////////

    private function toTools(array $tools) : array {
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

    private function toToolChoice(string|array $toolChoice, array $tools) : array {
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