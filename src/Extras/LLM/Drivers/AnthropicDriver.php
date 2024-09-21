<?php
namespace Cognesy\Instructor\Extras\LLM\Drivers;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Contracts\CanInfer;
use Cognesy\Instructor\Extras\LLM\LLMConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class AnthropicDriver implements CanInfer
{
    public function __construct(
        protected Client $client,
        protected LLMConfig $config
    ) {}

    public function infer(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : ApiResponse {
        $response = $this->client->post($this->getEndpointUrl(), [
            'headers' => $this->getRequestHeaders(),
            'json' => $this->getRequestBody(
                $messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode
            ),
        ]);
        return $this->toResponse($response->getBody()->getContents());
    }

    // INTERNAL /////////////////////////////////////////////

    protected function getEndpointUrl() : string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }

    private function getRequestHeaders() : array {
        return array_filter([
            'x-api-key' => $this->config->apiKey,
            'content-type' => 'application/json',
            'accept' => 'application/json',
            'anthropic-version' => $this->config->metadata['apiVersion'] ?? '',
            'anthropic-beta' => $this->config->metadata['beta'] ?? '',
        ]);
    }

    /**
     * @throws GuzzleException
     */
    protected function getRequestBody(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : array {
        $request = array_filter(array_merge([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $options['max_tokens'] ?? $this->config->maxTokens,
            'system' => Messages::fromArray($messages)
                ->forRoles(['system'])
                ->toString(),
            'messages' => Messages::fromArray($messages)
                ->exceptRoles(['system'])
                ->toNativeArray(
                    clientType: $this->config->clientType,
                    mergePerRole: true
                ),
        ], $options));

        switch($mode) {
            case Mode::Tools:
                $request['tools'] = $this->toTools($tools);
                $request['tool_choice'] = $this->toToolChoice($toolChoice, $tools);
                break;
            case Mode::Json:
            case Mode::JsonSchema:
                break;
        }

        return $request;
    }

    protected function toTools(array $tools) : array {
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

    protected function toToolChoice(string|array $toolChoice, array $tools) : array|string {
        return match(true) {
            empty($tools) => '',
            is_array($toolChoice) => [
                'type' => 'tool',
                'name' => $toolChoice['function']['name'],
            ],
            empty($toolChoice) => [
                'type' => 'auto',
            ],
            default => [
                'type' => $toolChoice,
            ],
        };
    }

    protected function toResponse(string $response) : ApiResponse {
        return $this->config->clientType->toApiResponse($response);
    }
}
