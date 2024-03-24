<?php

namespace Cognesy\Instructor\Interceptor\OpenRouter;

use Cognesy\Instructor\Interceptor\InterceptorClient;

class OpenRouterClient extends InterceptorClient
{
    public function __construct()
    {
        $this->addProcessor(new AddMissingFields());
    }
}