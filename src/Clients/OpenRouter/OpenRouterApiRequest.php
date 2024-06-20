<?php
namespace Cognesy\Instructor\Clients\OpenRouter;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesResponse;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesRequestBody;

class OpenRouterApiRequest extends ApiRequest
{
    use HandlesRequestBody;
    use HandlesResponse;
}