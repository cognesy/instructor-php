<?php
namespace Tests;

use Cognesy\Instructor\Extras\LLM\Data\ApiResponse;
use Cognesy\Instructor\Extras\LLM\Inference;
use Mockery;

class MockLLM
{
    static public function get(array $args) : Inference {
        $mockLLM = Mockery::mock(Inference::class);
        $list = [];
        foreach ($args as $arg) {
            $list[] = self::makeFunc($arg);
        }
        //$mockLLM->shouldReceive('handle')->andReturnUsing(fn() => new OpenAIApiRequest());
        $mockLLM->shouldReceive('handle')->andReturn($mockLLM);
        $mockLLM->shouldReceive('getEndpointUrl')->andReturn('');
        $mockLLM->shouldReceive('getRequestHeaders')->andReturn([]);
        $mockLLM->shouldReceive('getRequestBody')->andReturnUsing([]);
        $mockLLM->shouldReceive('toApiResponse')->andReturn(...$list);
        $mockLLM->shouldReceive('toPartialApiResponse')->andReturn($mockLLM);
        $mockLLM->shouldReceive('getData')->andReturn('');
        return $mockLLM;
    }

    static private function makeFunc(string $json) {
        return fn() => new ApiResponse(
            content: $json,
        );
    }
}
