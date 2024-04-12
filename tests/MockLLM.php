<?php

namespace Tests;

use Cognesy\Instructor\ApiClient\Data\Responses\ApiResponse;
use Cognesy\Instructor\Contracts\CanCallApiClient;
use Cognesy\Instructor\Utils\Result;
use Mockery;

class MockLLM
{
    static public function get(array $args) : ?CanCallApiClient {
        $mockLLM = Mockery::mock(RequestHandler::class);
        $list = [];
        foreach ($args as $arg) {
            $list[] = self::makeFunc($arg);
        }
        $mockLLM->shouldReceive('toolsCall')->andReturnUsing(...$list);
        return $mockLLM;
    }

    static private function makeFunc(string $json) {
        return fn() => Result::success(new ApiResponse(
            content: $json,
        ));
    }
}
