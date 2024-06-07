<?php
namespace Cognesy\Instructor\Clients\OpenAI;

use Cognesy\Instructor\ApiClient\Context\ApiRequestContext;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Saloon\Enums\Method;

class OpenAIApiRequest extends ApiRequest
{
    use Traits\HandlesTools;
    use Traits\HandlesResponseFormat;
    use Traits\HandlesResponse;

    public function __construct(
        array $body = [],
        string $endpoint = '',
        Method $method = Method::POST,
        //
        ApiRequestContext $context = null,
        array $options = [], // to consolidate into $context?
        array $data = [], // to consolidate into $context?
    ) {
        if ($this->isStreamed()) {
            $body['stream_options']['include_usage'] = true;
        }
        parent::__construct(
            body: $body,
            endpoint: $endpoint,
            method: $method,
            context: $context,
            options: $options,
            data: $data,
        );
    }
}