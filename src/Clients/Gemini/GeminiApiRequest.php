<?php
namespace Cognesy\Instructor\Clients\Gemini;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;
use Saloon\Enums\Method;

class GeminiApiRequest extends ApiRequest
{
    use Traits\HandlesRequestBody;
    use Traits\HandlesResponse;

    protected string $defaultEndpoint = '/models/{model}:generateContent';
    protected string $streamEndpoint = '/models/{model}:streamGenerateContent?alt=sse';

    private string $system = '';

    public function __construct(
        array $body = [],
        string $endpoint = '',
        Method $method = Method::POST,
        ApiRequestConfig $requestConfig = null,
        array $data = [],
    ) {
        parent::__construct($body, $endpoint, $method, $requestConfig, $data);
    }

    protected function defaultBody(): array {
        $system = Messages::fromArray($this->messages)
            ->forRoles(['system'])
            ->toString();
        $system = empty($system) ? [] : ['parts' => ['text' => $system]];
        $contents = Messages::fromArray($this->messages)
            ->exceptRoles(['system'])
            ->toNativeArray(ClientType::fromRequestClass($this), mergePerRole: true);
        $body = array_filter(
            [
                'systemInstruction' => $system,
                'contents' => $contents,
                'generationConfig' => $this->options(),
            ],
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }

    public function resolveEndpoint(): string {
        return match(true) {
            $this->isStreamed() => str_replace('{model}', $this->model, $this->streamEndpoint),
            default => str_replace('{model}', $this->model, $this->defaultEndpoint),
        };
    }
}
