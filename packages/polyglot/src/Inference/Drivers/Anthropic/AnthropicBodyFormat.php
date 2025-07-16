<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Anthropic;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanMapMessages;
use Cognesy\Polyglot\Inference\Contracts\CanMapRequestBody;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Utils\Messages\Enums\MessageRole;
use Cognesy\Utils\Messages\Messages;

class AnthropicBodyFormat implements CanMapRequestBody
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
        protected bool $parallelToolCalls = false
    ) {}

    public function toRequestBody(InferenceRequest $request) : array {
        $options = array_merge($this->config->options, $request->options());

        $this->parallelToolCalls = $options['parallel_tool_calls'] ?? false;
        unset($options['parallel_tool_calls']);

        $requestBody = array_merge(array_filter([
            'model' => $request->model() ?: $this->config->model,
            'max_tokens' => $options['max_tokens'] ?? $this->config->maxTokens,
            'system' => $this->toSystemMessages($request),
            'messages' => $this->toMessages($request),
        ]), $options);

        // Anthropic does not support response_format or JSON/JSON Schema mode
        unset($requestBody['response_format']);

        if ($request->hasTools()) {
            $requestBody['tools'] = $this->toTools($request);
            $requestBody['tool_choice'] = $this->toToolChoice($request);
        }

        return array_filter($requestBody, fn($value) => $value !== null && $value !== [] && $value !== '');
    }

    // INTERNAL /////////////////////////////////////////////

    protected function toTools(InferenceRequest $request) : array {
        $cachedTools = $request->cachedContext()?->tools() ?? [];
        $tools = $request->tools();

        $result = [];
        foreach ($cachedTools as $tool) {
            $result[] = $this->toAnthropicTool($tool);
        }

        $count = count($result);
        if ($count > 0) {
            // set cache marker on last tool entry
            $result[$count-1]['cache_control'] = ['type' => 'ephemeral'];
        }

        foreach ($tools as $tool) {
            $result[] = $this->toAnthropicTool($tool);
        }

        return $result;
    }

    private function toAnthropicTool(array $tool) : array {
        $anthropicTool = [
            'name' => $tool['function']['name'] ?? '',
            'description' => $tool['function']['description'] ?? '',
            'input_schema' => $tool['function']['parameters'] ?? [],
        ];
        return $anthropicTool;
    }

    protected function toToolChoice(InferenceRequest $request) : array {
        $cachedToolChoice = $request->cachedContext()?->toolChoice();
        $toolChoice = $request->toolChoice() ?: $cachedToolChoice;
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

    protected function toSystemMessages(InferenceRequest $request) : array {
        $cachedMessages = $request->cachedContext()?->messages() ?? [];

        $systemCached = Messages::fromArray($cachedMessages)
            ->headWithRoles([MessageRole::System, MessageRole::Developer])
            ->appendContentField('cache_control', ['type' => 'ephemeral']);

        $systemMessages = Messages::fromArray($request->messages())
            ->headWithRoles([MessageRole::System, MessageRole::Developer]);

        $messages = $systemCached->appendMessages($systemMessages);

        return $this->toSystemEntries($messages);
    }

    protected function toSystemEntries(
        Messages $messages,
    ) : array {
        $textFragments = [];
        foreach ($messages->all() as $message) {
            foreach ($message->content()->parts() as $contentPart) {
                // TODO: what about non-text content - e.g. images? caching should support them too
                if (!$contentPart->hasText() || $contentPart->isEmpty()) {
                    continue;
                }
                $textFragments[] = match(true) {
                    $contentPart->has('cache_control') => [
                        'type' => 'text',
                        'text' => $contentPart->toString(),
                        'cache_control' => ["type" => "ephemeral"]
                    ],
                    default => [
                        'type' => 'text',
                        'text' => $contentPart->toString(),
                    ],
                };
            }
        }
        return $textFragments;
    }

    protected function toMessages(InferenceRequest $request) : array {
        $cachedMessages = $request->cachedContext()?->messages() ?? [];

        $postSystemCached = Messages::fromArray($cachedMessages)
            ->tailAfterRoles([MessageRole::System, MessageRole::Developer])
            ->appendContentField('cache_control', ['type' => 'ephemeral']);

        $postSystemMessages = Messages::fromArray($request->messages())
            ->tailAfterRoles([MessageRole::System, MessageRole::Developer]);

        $messages = $postSystemCached
            ->appendMessages($postSystemMessages)
            ->toArray();

        return $this->messageFormat->map($messages);
    }
}