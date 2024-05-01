<?php

namespace Cognesy\Instructor\Services;

use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Generator;

class ProcessorService
{
    public function processResponse(ApiResponse $response) : mixed {
        return '';
    }

    public function processStreamResponse(Generator $stream) : Generator {
        yield null;
    }
}
