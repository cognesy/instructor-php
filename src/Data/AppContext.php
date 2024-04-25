<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;

class AppContext
{
    public function __construct(
        public ?ResponseModel $responseModel = null,
        public ?CanCallApi $client = null,
        public ?Request $request = null,
    ) {}
}
