<?php
namespace Cognesy\Instructor\Clients\FireworksAI;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesResponse;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesRequestBody;

class FireworksAIApiRequest extends ApiRequest
{
    use HandlesRequestBody;
    use HandlesResponse;
}