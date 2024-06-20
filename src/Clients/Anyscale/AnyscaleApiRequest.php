<?php
namespace Cognesy\Instructor\Clients\Anyscale;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesResponse;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesRequestBody;

class AnyscaleApiRequest extends ApiRequest
{
    use HandlesRequestBody;
    use HandlesResponse;
}