<?php

namespace Tests;

use Cognesy\Instructor\LLMs\Data\FunctionCall;
use Cognesy\Instructor\LLMs\Data\LLMResponse;
use Cognesy\Instructor\LLMs\OpenAI\ToolsMode\OpenAIToolCaller;
use Cognesy\Instructor\Utils\Result;
use Mockery;

class MockLLM
{
    static public function get(array $args) : ?OpenAIToolCaller {
        $mockLLM = Mockery::mock(OpenAIToolCaller::class);
        $list = [];
        foreach ($args as $arg) {
            $list[] = self::makeFunc($arg);
        }
        $mockLLM->shouldReceive('callFunction')->andReturnUsing(...$list);
        return $mockLLM;
    }

    static private function makeFunc(string $json) {
        return fn() => Result::success(new LLMResponse(
            functionCalls: [
                new FunctionCall(
                    toolCallId: '1',
                    functionName: 'callFunction',
                    functionArguments: $json,
                ),
            ],
            finishReason: 'success',
            rawData: null,
        ));
    }
}
