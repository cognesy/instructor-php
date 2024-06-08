<?php
namespace Cognesy\Instructor\Clients\Gemini;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;

class GeminiApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesTools;
    use Traits\HandlesResponseFormat;

    protected string $defaultEndpoint = '/models/{model}:generateContent';

    protected function defaultBody(): array {
        return array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model,
                    'tools' => $this->tools()
                ],
            )
        );
    }

    public function resolveEndpoint(): string {
        return str_replace('{model}', $this->model, $this->defaultEndpoint);
    }
}
