<?php
namespace Cognesy\Instructor\Clients\Gemini;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Events\ApiClient\RequestBodyCompiled;

class GeminiApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesTools;
    use Traits\HandlesResponseFormat;

    protected string $defaultEndpoint = '/models/{model}:generateContent';

    protected function defaultBody(): array {
        $body = array_filter(
            array_merge(
                $this->requestBody,
                [
                    'model' => $this->model(),
                    'tools' => $this->tools()
                ],
            )
        );
        $this->requestConfig()->events()->dispatch(new RequestBodyCompiled($body));
        return $body;
    }

    public function resolveEndpoint(): string {
        return str_replace('{model}', $this->model, $this->defaultEndpoint);
    }
}
