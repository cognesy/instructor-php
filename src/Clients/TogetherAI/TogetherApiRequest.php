<?php
namespace Cognesy\Instructor\Clients\TogetherAI;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesResponse;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesRequestBody;

class TogetherApiRequest extends ApiRequest
{
    use HandlesRequestBody;
    use HandlesResponse;
}