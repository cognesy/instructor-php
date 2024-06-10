<?php
namespace Cognesy\Instructor\Clients\Groq;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesResponse;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesRequestBody;

class GroqApiRequest extends ApiRequest
{
    use HandlesRequestBody;
    use HandlesResponse;
}