<?php
namespace Cognesy\Instructor\Clients\Mistral;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesResponse;

class MistralApiRequest extends ApiRequest
{
    use Traits\HandlesTools;
    use Traits\HandlesResponseFormat;
    use HandlesResponse;
}