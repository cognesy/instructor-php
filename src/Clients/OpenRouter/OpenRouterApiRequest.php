<?php
namespace Cognesy\Instructor\Clients\OpenRouter;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesResponse;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesRequestBody;

class OpenRouterApiRequest extends ApiRequest
{
    use HandlesRequestBody;
    use HandlesResponse;
}