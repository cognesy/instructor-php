<?php
namespace Cognesy\Instructor\Clients\OpenAI;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Enums\Mode;

class OpenAIApiRequest extends ApiRequest
{
    public function __construct(
        array            $body = [],
        string           $endpoint = '',
        string           $method = 'POST',
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

    // OVERRIDES /////////////////////////////////////////////////

    protected function getResponseFormat(): array {
        if ($this->mode == Mode::Json) {
            return ['type' => 'json_object'];
        }
        return $this->responseFormat;
    }
}
