<?php

namespace Cognesy\Instructor\ApiClient;

class JsonResponse
{
    public function __construct(
        public string $json,
        public ?string $functionName,
        public array $responseData,
    ) {}
}