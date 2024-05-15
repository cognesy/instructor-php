<?php

namespace Tests;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Requests\ApiRequest;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Mockery;

class MockLLM
{
    static public function get(array $args) : CanCallApi {
        $mockLLM = Mockery::mock(OpenAIClient::class);
        $list = [];
        foreach ($args as $arg) {
            $list[] = self::makeFunc($arg);
        }
        $mockLLM->shouldReceive('createApiRequest')->andReturnUsing(fn() => new ApiRequest());
        $mockLLM->shouldReceive('getApiRequest')->andReturnUsing(fn() => new ApiRequest());
        $mockLLM->shouldReceive('get')->andReturnUsing(...$list);
        $mockLLM->shouldReceive('toolsCall')->andReturn($mockLLM);
        $mockLLM->shouldReceive('withApiRequest')->andReturn($mockLLM);
        $mockLLM->shouldReceive('withApiRequestFactory')->andReturn($mockLLM);
        $mockLLM->shouldReceive('withDebug')->andReturn($mockLLM);
        $mockLLM->shouldReceive('withEventDispatcher')->andReturn($mockLLM);
        return $mockLLM;
    }

    static private function makeFunc(string $json) {
        return fn() => new ApiResponse(
            content: $json,
        );
    }
}
