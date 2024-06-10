<?php
namespace Cognesy\Instructor\Clients\Anyscale;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesResponse;
use Cognesy\Instructor\Clients\OpenAI\Traits\HandlesRequestBody;

class AnyscaleApiRequest extends ApiRequest
{
    use HandlesRequestBody;
    use HandlesResponse;
}