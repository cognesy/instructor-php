<?php
namespace Cognesy\Instructor\Clients\Groq;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesResponse;

class GroqApiRequest extends ApiRequest
{
    use Traits\HandlesRequestBody;
    use HandlesResponse;
}