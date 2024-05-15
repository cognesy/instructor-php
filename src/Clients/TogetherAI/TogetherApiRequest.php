<?php

namespace Cognesy\Instructor\Clients\TogetherAI;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesResponse;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesResponseFormat;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesTools;

class TogetherApiRequest extends ApiRequest
{
    use HandlesTools;
    use HandlesResponseFormat;
    use HandlesResponse;
}