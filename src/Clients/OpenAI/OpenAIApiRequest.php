<?php
namespace Cognesy\Instructor\Clients\OpenAI;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Enums\Mode;
use Saloon\Enums\Method;

class OpenAIApiRequest extends ApiRequest
{
    use Traits\HandlesRequestBody;
    use Traits\HandlesResponse;

    public function __construct(
        array            $body = [],
        string           $endpoint = '',
        Method           $method = Method::POST,
        ApiRequestConfig $requestConfig = null,
        array            $data = [],
    ) {
        if ($this->isStreamed()) {
            $body['stream_options']['include_usage'] = true;
        }
        parent::__construct(
            body: $body,
            endpoint: $endpoint,
            method: $method,
            requestConfig: $requestConfig,
            data: $data,
        );
    }

    public function messages(): array {
        if ($this->noScript()) {
            return $this->messages;
        }

        if ($this->script->section('examples')->notEmpty()) {
            $this->script->section('pre-examples')->appendMessage([
                'role' => 'assistant',
                'content' => 'Provide examples.',
            ]);
        }
        $this->script->section('pre-input')->appendMessage([
            'role' => 'assistant',
            'content' => "Provide input.",
        ]);

        if($this->mode->is(Mode::Tools)) {
            unset($this->scriptContext['json_schema']);
        }

        return $this->script
            ->withContext($this->scriptContext)
            ->select(['prompt', 'pre-examples', 'examples', 'pre-input', 'messages', 'input', 'retries'])
            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }
}