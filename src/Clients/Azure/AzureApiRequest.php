<?php
namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesResponse;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesResponseFormat;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesTools;
use Saloon\Enums\Method;

class AzureApiRequest extends ApiRequest
{
    use HandlesTools;
    use HandlesResponseFormat;
    use HandlesResponse;

    public function __construct(
        array            $body = [],
        string           $endpoint = '',
        Method           $method = Method::POST,
        //
        ApiRequestConfig $requestConfig = null,
        array            $data = [], // to consolidate into $config?
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