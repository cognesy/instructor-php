<?php
namespace Cognesy\Instructor\Extras\LLM\Drivers;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Contracts\CanInfer;
use Cognesy\Instructor\Extras\LLM\LLMConfig;
use Cognesy\Instructor\Utils\Arrays;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class GeminiDriver implements CanInfer
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
        return $this->client->post($this->getEndpointUrl($model, $options), [
            'headers' => $this->getRequestHeaders(),
            'json' => $this->getRequestBody(
                $messages, $model, $tools, $toolChoice, $responseFormat, $options, $mode
            ),
            'connect_timeout' => $this->config->connectTimeout ?? 3,
            'timeout' => $this->config->requestTimeout ?? 30,
        ]);
    }

    protected function getEndpointUrl(string $model = '', array $options = []): string {
        if ($options['stream'] ?? false) {
            $this->config->endpoint = '/models/{model}:streamGenerateContent?alt=sse';
        } else {
            $this->config->endpoint = '/models/{model}:generateContent';
        }
        return str_replace(
            search: "{model}",
            replace: $model ?: $this->config->model,
            subject: "{$this->config->apiUrl}{$this->config->endpoint}?key={$this->config->apiKey}");
    }

    protected function getRequestHeaders() : array {
        return [
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
        $request = array_filter([
            'systemInstruction' => $this->toSystem($messages),
            'contents' => $this->toMessages($messages),
            'generationConfig' => $this->toOptions($options, $responseFormat, $mode),
        ]);

        if ($mode == Mode::Tools) {
            $request['tools'] = $this->toTools($tools);
            $request['tool_config'] = $this->toToolChoice($toolChoice);
        }

        return $request;
    }

    private function toSystem(array $messages) : array {
        $system = Messages::fromArray($messages)
            ->forRoles(['system'])
            ->toString();

        return empty($system) ? [] : ['parts' => ['text' => $system]];
    }

    private function toMessages(array $messages) : array {
        return Messages::fromArray($messages)
            ->exceptRoles(['system'])
            ->toNativeArray(
                clientType: $this->config->clientType,
                mergePerRole: true
            );
    }

    protected function toOptions(
        array $options,
        array $responseFormat,
        Mode $mode,
    ) : array {
        return array_filter([
            "responseMimeType" => $this->toResponseFormat($mode),
            "responseSchema" => $this->toResponseSchema($responseFormat, $mode),
            "candidateCount" => 1,
            "maxOutputTokens" => $options['max_tokens'] ?? $this->config->maxTokens,
            "temperature" => $options['temperature'] ?? 1.0,
        ]);
    }

    protected function toTools(array $tools) : array {
        return ['function_declarations' => array_map(
            callback: fn($function) => $this->removeDisallowedEntries($function['function']),
            array: $tools
        )];
    }

    protected function toToolChoice(array $toolChoice): string|array {
        return match(true) {
            empty($toolChoice) => ["function_calling_config" => ["mode" => "ANY"]],
            is_array($toolChoice) => [
                "function_calling_config" => array_filter([
                    "mode" => "ANY",
                    "allowed_function_names" => $toolChoice['function']['name'] ?? [],
                ]),
            ],
            default => ["function_calling_config" => ["mode" => "ANY"]],
        };
    }

    protected function toResponseFormat(Mode $mode): string {
        return match($mode) {
            Mode::Text => "text/plain",
            Mode::MdJson => "text/plain",
            default => "application/json",
        };
    }

    protected function toResponseSchema(array $responseFormat, Mode $mode) : array {
        return match($mode) {
            Mode::MdJson => [],
            Mode::Json => $this->removeDisallowedEntries($responseFormat['schema'] ?? []),
            Mode::JsonSchema => $this->removeDisallowedEntries($responseFormat['schema'] ?? []),
            default => [],
        };
    }

    protected function removeDisallowedEntries(array $jsonSchema) : array {
        return Arrays::removeRecursively($jsonSchema, [
            'title',
            'x-php-class',
            'additionalProperties',
        ]);
    }

    protected function toResponse(string $response) : ApiResponse {
        return $this->config->clientType->toApiResponse($response);
    }
}
