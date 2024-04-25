<?php

namespace Cognesy\Instructor\ApiClient\Factories;

use Cognesy\Instructor\ApiClient\Context\ApiRequestContext;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;

class ApiRequestFactory
{
    public function __construct(
        private ApiRequestContext $context,
    ) {}

    public function fromClass(
        string $requestClass,
        array $args
    ) : ApiRequest {
        return (new $requestClass(...$args))
            ->withContext($this->context);
    }
}