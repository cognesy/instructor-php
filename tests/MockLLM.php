<?php
namespace Tests;

use Cognesy\Instructor\Features\Http\Contracts\CanAccessResponse;
use Cognesy\Instructor\Features\LLM\Contracts\CanHandleInference;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Drivers\OpenAIDriver;
use Mockery;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class MockLLM
{
    static public function get(array $args) : CanHandleInference {
        $mockLLM = Mockery::mock(OpenAIDriver::class);
        $mockResponse = Mockery::mock(CanAccessResponse::class, ResponseInterface::class, StreamInterface::class, MessageInterface::class);
        $list = [];
        foreach ($args as $arg) {
            $list[] = self::makeFunc($arg);
        }
        //$mockLLM->shouldReceive('handle')->andReturnUsing(fn() => new OpenAIApiRequest());
        $mockLLM->shouldReceive('getData')->andReturn('');
        $mockLLM->shouldReceive('handle')->andReturn($mockResponse);
        $mockLLM->shouldReceive('getEndpointUrl')->andReturn('');
        $mockLLM->shouldReceive('getRequestHeaders')->andReturn([]);
        $mockLLM->shouldReceive('getRequestBody')->andReturnUsing([]);
        $mockLLM->shouldReceive('toLLMResponse')->andReturnUsing(...$list);
        $mockLLM->shouldReceive('toPartialLLMResponse')->andReturn($mockLLM);


        $mockResponse->shouldReceive('getContents')->andReturn($mockResponse);
        $mockResponse->shouldReceive('streamContents')->andReturn($mockResponse);

        return $mockLLM;
    }

    static private function makeFunc(string $json) {
        return fn() => new LLMResponse(
            content: $json,
        );
    }
}
