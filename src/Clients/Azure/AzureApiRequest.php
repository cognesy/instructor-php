<?php
namespace Cognesy\Instructor\Clients\Azure;

use Cognesy\Instructor\ApiClient\RequestConfig\ApiRequestConfig;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesResponse;
use Saloon\Enums\Method;

class AzureApiRequest extends ApiRequest
{
    use Traits\HandlesRequestBody;
    use HandlesResponse;

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