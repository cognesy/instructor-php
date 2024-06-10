<?php
namespace Cognesy\Instructor\Clients\Ollama;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesResponse;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesRequestBody;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;

class OllamaApiRequest extends ApiRequest
{
    use HandlesRequestBody;
    use HandlesResponse;

    protected function defaultBody(): array {
        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'messages' => $this->messages(),
                    'tools' => $this->tools(),
                    'tool_choice' => $this->getToolChoice(),
                ],
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }

    public function messages(): array {
        return $this->script
            ->withContext($this->scriptContext)
            ->select(['system', 'prompt', 'examples', 'messages', 'input', 'retries'])
            ->toNativeArray(ClientType::fromRequestClass(static::class));
    }
}