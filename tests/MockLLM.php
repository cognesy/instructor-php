<?php

namespace Tests;

use Cognesy\Instructor\ApiClient\Contracts\CanCallTools;
use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Data\FunctionCall;
use Cognesy\Instructor\Data\LLMResponse;
use Cognesy\Instructor\Utils\Result;
use Mockery;

class MockLLM
{
    static public function get(array $args) : ?CanCallFunction {
        $mockLLM = Mockery::mock(CanCallFunction::class);
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
                    functionArgsJson: $json,
                ),
            ],
            finishReason: 'success',
            rawResponse: null,
        ));
    }
}
