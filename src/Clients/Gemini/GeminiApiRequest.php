<?php
namespace Cognesy\Instructor\Clients\Gemini;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;
use Cognesy\Instructor\Utils\Arrays;

class GeminiApiRequest extends ApiRequest
{
    protected string $defaultEndpoint = '/models/{model}:generateContent';
    protected string $streamEndpoint = '/models/{model}:streamGenerateContent?alt=sse';

    public function __construct(
        array $body = [],
        string $endpoint = '',
        string $method = 'POST',
        ApiRequestConfig $requestConfig = null,
        array $data = [],
    ) {
        parent::__construct($body, $endpoint, $method, $requestConfig, $data);
    }

    public function resolveEndpoint(): string {
        return match(true) {
            $this->isStreamed() => str_replace('{model}', $this->model, $this->streamEndpoint),
            default => str_replace('{model}', $this->model, $this->defaultEndpoint),
        };
    }

    protected function defaultBody(): array {
        $system = Messages::fromArray($this->messages)
            ->forRoles(['system'])
            ->toString();
        $contents = Messages::fromArray($this->messages)
            ->exceptRoles(['system'])
            ->toNativeArray(ClientType::fromRequestClass($this), mergePerRole: true);
        $body = array_filter(
            [
                'systemInstruction' => empty($system) ? [] : ['parts' => ['text' => $system]],
                'contents' => $contents,
                'generationConfig' => $this->options(),
            ],
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }

    public function tools(): array {
        return $this->tools;
    }

    public function toolChoice(): string|array {
        if (empty($this->tools)) {
            return '';
        }
        return $this->toolChoice ?: 'auto';
    }

    public function responseFormat(): array {
        return match($this->mode) {
            Mode::MdJson => ["text/plain"],
            default => ["application/json"],
        };
    }

    public function responseSchema() : array {
        return Arrays::removeRecursively($this->jsonSchema, [
            'title',
            'x-php-class',
            'additionalProperties',
        ]);
    }

    public function options() : array {
        return array_filter([
//            "stopSequences" => $this->requestBody['stopSequences'] ?? [],
            "responseMimeType" => $this->responseFormat()[0],
            "responseSchema" => match($this->mode) {
                Mode::MdJson => '',
                Mode::Json => $this->responseSchema(),
                Mode::JsonSchema => $this->responseSchema(),
                default => '',
            },
            "candidateCount" => 1,
            "maxOutputTokens" => $this->maxTokens,
            "temperature" => $this->requestBody['temperature'] ?? 1.0,
//            "topP" => "float",
//            "topK" => "integer"
        ]);
    }
}
