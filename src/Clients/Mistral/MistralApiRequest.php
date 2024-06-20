<?php
namespace Cognesy\Instructor\Clients\Mistral;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesResponse;

class MistralApiRequest extends ApiRequest
{
    use HandlesResponse;
    use Traits\HandlesRequestBody;
}