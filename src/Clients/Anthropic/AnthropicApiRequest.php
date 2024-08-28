<?php
namespace Cognesy\Instructor\Clients\Anthropic;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;


class AnthropicApiRequest extends ApiRequest
{
    protected string $defaultEndpoint = '/messages';

    protected function defaultBody(): array {
        $system = Messages::fromArray($this->messages)
            ->forRoles(['system'])
            ->toString();

        $messages = Messages::fromArray($this->messages)
            ->exceptRoles(['system'])
            ->toNativeArray(
                clientType: ClientType::fromRequestClass($this),
                mergePerRole: true
            );

        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'max_tokens' => $this->maxTokens,
                    'system' => $system,
                    'messages' => $messages,
                    'tools' => $this->tools(),
                    'tool_choice' => $this->getToolChoice(),
                ],
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
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
        return $this->jsonSchema ?? [];
    }
}
