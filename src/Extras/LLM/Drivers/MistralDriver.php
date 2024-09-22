<?php
namespace Cognesy\Instructor\Extras\LLM\Drivers;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Contracts\CanInfer;
use Cognesy\Instructor\Extras\LLM\LLMConfig;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class MistralDriver implements CanInfer
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
        Mode $mode = Mode::Json,
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

    protected function getEndpointUrl(): string {
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
        $request = array_filter(array_merge([
            'model' => $model ?: $this->config->model,
            'max_tokens' => $this->config->maxTokens,
            'messages' => $messages,
        ], $options));

        switch($mode) {
            case Mode::Tools:
                $request['tools'] = $tools;
                $request['tool_choice'] = 'any';
                break;
            case Mode::Json:
            case Mode::JsonSchema:
                $request['response_format'] = ['type' => 'json_object'];
                break;
        }

        return $request;
    }

    protected function toResponse(string $response) : ApiResponse {
        return $this->config->clientType->toApiResponse($response);
    }
}