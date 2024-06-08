<?php
namespace Cognesy\Instructor\Clients\OpenAI;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Saloon\Enums\Method;

class OpenAIApiRequest extends ApiRequest
{
    use Traits\HandlesTools;
    use Traits\HandlesResponseFormat;
    use Traits\HandlesResponse;
    use Traits\HandlesScripts;

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
}