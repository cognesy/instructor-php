<?php
namespace Cognesy\Instructor\Clients\Cohere;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Override;

class CohereApiRequest extends ApiRequest
{
    use Traits\HandlesResponse;
    use Traits\HandlesTools;
    use Traits\HandlesResponseFormat;

    protected string $defaultEndpoint = '/chat';

    #[Override]
    protected function defaultBody(): array {
        return array_filter(
            array_merge(
                [
                    'model' => $this->model,
                    'tools' => $this->tools()
                ],
                $this->options,
            )
        );
    }
}
