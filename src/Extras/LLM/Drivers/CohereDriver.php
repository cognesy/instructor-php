<?php
namespace Cognesy\Instructor\Extras\LLM\Drivers;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Contracts\CanInfer;
use Cognesy\Instructor\Extras\LLM\LLMConfig;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class CohereDriver implements CanInfer
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
        $response = $this->createResponse($messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode);
        return $this->toResponse($response->getBody()->getContents());
    }

    // INTERNAL /////////////////////////////////////////////

    protected function createResponse(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : ResponseInterface {
        return $this->client->post($this->getEndpointUrl(), [
            'headers' => $this->getRequestHeaders(),
            'json' => $this->getRequestBody(
                $messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode
            ),
            'connect_timeout' => $this->config->connectTimeout ?? 3,
            'timeout' => $this->config->requestTimeout ?? 30,
        ]);
    }

    protected function getEndpointUrl() : string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }

    protected function getRequestHeaders() : array {
        return [
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json',
        ];
    }

    protected function getRequestBody(
        array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = '',
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text,
    ) : array {
        $system = '';
        $chatHistory = [];

        $request = array_filter(array_merge([
            'model' => $model ?: $this->config->model,
            'preamble' => $system,
            'chat_history' => $chatHistory,
            'message' => Messages::asString($messages),
        ], $options));

        switch($mode) {
            case Mode::Tools:
                $request['tools'] = $this->toTools($tools);
                break;
            case Mode::Json:
            case Mode::JsonSchema:
                $request['response_format'] = [
                    'type' => 'json_object',
                    'schema' => $responseFormat['schema'] ?? [],
                ];
                break;
        }

        return $request;
    }

    protected function toTools(array $tools): array {
        $result = [];
        foreach ($tools as $tool) {
            $parameters = [];
            foreach ($tool['function']['parameters']['properties'] as $name => $param) {
                $parameters[] = array_filter([
                    'name' => $name,
                    'description' => $param['description'] ?? '',
                    'type' => $this->toCohereType($param),
                    'required' => in_array(
                        needle: $name,
                        haystack: $tools['function']['parameters']['required'] ?? [],
                    ),
                ]);
            }
            $result[] = [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'] ?? '',
                'parameters_definitions' => $parameters,
            ];
        }
        return $result;
    }

    protected function toCohereType(array $param) : string {
        return match($param['type']) {
            'string' => 'str',
            'number' => 'float',
            'integer' => 'int',
            'boolean' => 'bool',
            'array' => throw new \Exception('Array type not supported by Cohere'),
            'object' => throw new \Exception('Object type not supported by Cohere'),
            default => throw new \Exception('Unknown type'),
        };
    }

    protected function toResponse(string $response) : ApiResponse {
        return $this->config->clientType->toApiResponse($response);
    }
}