<?php
namespace Cognesy\Instructor\Extras\LLM\Traits;

use InvalidArgumentException;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Utils\Settings;
use GuzzleHttp\Exception\GuzzleException;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;

trait HasConnectors {
    public function respondTo(ApiRequest $request) : array {
        return match ($this->clientType) {
            ClientType::Anthropic => $this->viaAnthropic($request),
            ClientType::Azure => $this->viaAzureOpenAI($request),
            ClientType::Cohere => $this->viaCohere($request),
            ClientType::Fireworks => $this->viaFireworks($request),
            ClientType::Gemini => $this->viaGemini($request),
            ClientType::Groq => $this->viaGroq($request),
            ClientType::Mistral => $this->viaMistral($request),
            ClientType::Ollama => $this->viaOllama($request),
            ClientType::OpenAI => $this->viaOpenAI($request),
            ClientType::OpenAICompatible => $this->viaOpenAICompatible($request),
            ClientType::Together => $this->viaTogether($request),
            default => throw new InvalidArgumentException("Unknown client: {$this->client}"),
        };
    }

    // INTERNAL /////////////////////////////////////////////////

    /**
     * @throws GuzzleException
     */
    protected function viaAnthropic(ApiRequest $apiRequest): array {
        $url = "{$this->apiUrl}{$this->endpoint}";
        
        $system = Messages::fromArray($this->messages)
            ->forRoles(['system'])
            ->toString();
        
        $messages = Messages::fromArray($this->messages)
            ->exceptRoles(['system'])
            ->toNativeArray(
                clientType: $this->clientType,
                mergePerRole: true
            );
        
        $tools = [];
        foreach ($this->tools as $tool) {
            $tools[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'input_schema' => $tool['function']['parameters'],
            ];
        }

        $toolChoice = match(true) {
            empty($tools) => '',
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


        $request = [
            'headers' => array_filter([
                'x-api-key' => $this->apiKey,
                'content-type' => 'application/json',
                'accept' => 'application/json',
                'anthropic-version' => $this->metadata['apiVersion'] ?? '',
                'anthropic-beta' => $this->metadata['beta'] ?? '',
            ]),
            'json' => array_filter(array_merge([
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $system,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => $toolChoice,
            ], $this->options)),
        ];
        $response = $this->client->post($url, $request);
        $result = $response->getBody()->getContents();
        return $this->clientType->toApiResponse($result);
    }

    protected function viaAzureOpenAI(ApiRequest $apiRequest): array {
        // query param 'api-version' => $apiVersion

        return $this->viaOpenAICompatible($apiRequest);
    }

    /**
     * @throws GuzzleException
     */
    protected function viaCohere(ApiRequest $apiRequest): array {
        $url = "{$this->apiUrl}{$this->endpoint}";
        $options['input_type'] = $options['input_type'] ?? 'search_document';
        $system = '';
        $chatHistory = [];
        $messages = Messages::asString($this->messages());
        $tools = [];
        foreach ($this->tools as $tool) {
            $parameters = [];
            foreach ($tool['function']['parameters']['properties'] as $name => $param) {
                $parameters[] = array_filter([
                    'name' => $name,
                    'description' => $param['description'] ?? '',
                    'type' => match($param['type']) {
                        'string' => 'str',
                        'number' => 'float',
                        'integer' => 'int',
                        'boolean' => 'bool',
                        'array' => throw new \Exception('Array type not supported by Cohere'),
                        'object' => throw new \Exception('Object type not supported by Cohere'),
                        default => throw new \Exception('Unknown type'),
                    },
                    'required' => in_array($name, $this->tools['function']['parameters']['required']??[]),
                ]);
            }
            $tools[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'parameters_definitions' => $parameters,
            ];
        }
        $request = [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => array_filter(array_merge([
                'model' => $this->model,
                'preamble' => $system,
                'chat_history' => $chatHistory,
                'message' => $messages,
                'tools' => $tools,
                'response_format' => [
                    'type' => 'json_object',
                    'schema' => $this->jsonSchema,
                ],
            ], $options)),
        ];
        $response = $this->client->post($url, $request);
        $result = $response->getBody()->getContents();
        return $this->clientType->toApiResponse($result);
    }

    protected function viaFireworks(ApiRequest $apiRequest): array {
        return $this->viaOpenAICompatible($apiRequest);
    }

    /**
     * @throws GuzzleException
     */
    protected function viaGemini(ApiRequest $apiRequest): array {
        $url = str_replace("{model}", $this->model, "{$this->apiUrl}{$this->endpoint}?key={$this->apiKey}");
        $request = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'systemInstruction' => empty($system) ? [] : ['parts' => ['text' => $system]],
                'contents' => $contents,
                'generationConfig' => $this->options(),
            ],
        ];
        $response = $this->client->post($url, $request);
        $result = json_decode($response->getBody()->getContents(), true);
        return array_map(fn($item) => $item['values'], $result['embeddings']);
    }

    protected function viaGroq(ApiRequest $apiRequest): array {
        return $this->viaOpenAICompatible($apiRequest);
    }

    /**
     * @throws GuzzleException
     */
    protected function viaMistral(ApiRequest $apiRequest): array {
        $url = "{$this->apiUrl}{$this->endpoint}";
        $request = [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => array_filter(array_merge([
                'model' => $this->model(),
                'max_tokens' => $this->maxTokens,
                'messages' => $this->messages(),
            ], $this->options)),
        ];
        switch($this->mode) {
            case Mode::Tools:
                $request['tools'] = $this->tools;
                $request['tool_choice'] = 'any';
                break;
            case Mode::Json:
            case Mode::JsonSchema:
                $request['response_format'] = ['type' => 'json_object'];
                break;
        }
        $response = $this->client->post($url, $request);
        $result = json_decode($response->getBody()->getContents(), true);
        
        return array_map(fn($item) => $item['embedding'], $result['data']);
    }

    /**
     * @throws GuzzleException
     */
    protected function viaOllama(ApiRequest $apiRequest): array {
        return $this->viaOpenAICompatible($apiRequest);
    }

    protected function viaOpenAI(ApiRequest $apiRequest): array {
        return $this->viaOpenAI($apiRequest);
    }

    protected function viaOpenAICompatible(ApiRequest $apiRequest): array {
        $url = "{$this->apiUrl}{$this->endpoint}";
        $request = [
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => array_filter(array_merge([
                'model' => $this->model(),
                'max_tokens' => $this->maxTokens,
                'messages' => $this->messages(),
            ], $options)),
        ];
        switch($this->mode) {
            case Mode::Tools:
                $request['tools'] = $this->tools();
                $request['tool_choice'] = $this->getToolChoice();
                break;
            case Mode::Json:
            case Mode::JsonSchema:
                $request['response_format'] = $this->getResponseFormat();
                break;
        }
        $response = $this->client->post($url, $request);
        $result = json_decode($response->getBody()->getContents(), true);
        return array_map(fn($item) => $item['embedding'], $result['data']);
    }

    protected function viaTogether(ApiRequest $apiRequest): ApiResponse {
        return $this->viaOpenAICompatible($apiRequest);
    }
}
