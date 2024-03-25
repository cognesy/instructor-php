<?php

namespace Cognesy\Instructor\ApiClient;

class PartialJsonResponse
{
    public function __construct(
        public string $partialJson,
        public array $parsedJsonData,
        public array $responseData,
    ) {}
}