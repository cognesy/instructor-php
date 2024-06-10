<?php
namespace Tests;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Responses\ApiResponse;
use Cognesy\Instructor\Clients\OpenAI\OpenAIApiRequest;
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
        $mockLLM->shouldReceive('createApiRequest')->andReturnUsing(fn() => new OpenAIApiRequest());
        $mockLLM->shouldReceive('getApiRequest')->andReturnUsing(fn() => new OpenAIApiRequest());
        $mockLLM->shouldReceive('defaultModel')->andReturn('openai:gpt-4o');
        $mockLLM->shouldReceive('defaultMaxTokens')->andReturn('256');
        $mockLLM->shouldReceive('getModeRequestClass')->andReturn(OpenAIApiRequest::class);
        $mockLLM->shouldReceive('get')->andReturnUsing(...$list);
        $mockLLM->shouldReceive('request')->andReturn($mockLLM);
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
