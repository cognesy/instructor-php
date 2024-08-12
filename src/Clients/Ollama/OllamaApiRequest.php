<?php
namespace Cognesy\Instructor\Clients\Ollama;

use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesResponse;
use Cognesy\Instructor\ApiClient\Requests\Traits\HandlesRequestBody;

class OllamaApiRequest extends ApiRequest
{
    use HandlesRequestBody;
    use HandlesResponse;
}
