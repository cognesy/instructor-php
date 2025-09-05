<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Inference\LLMProvider;

function _inc_http(int $x): int { return $x + 1; }
function _dbl_http(int $x): int { return $x * 2; }

it('executes two tool calls returned by HTTP mocked response', function () {
    $http = (new HttpClientBuilder())
        ->withMock(function ($mock) {
            $mock->on()
                ->post('https://api.openai.com/v1/chat/completions')
                ->times(1)
                ->replyJson([
                    'choices' => [[
                        'message' => [
                            'content' => '',
                            'tool_calls' => [
                                [ 'id' => 'c1', 'type' => 'function', 'function' => [ 'name' => '_inc_http', 'arguments' => '{"x":1}' ] ],
                                [ 'id' => 'c2', 'type' => 'function', 'function' => [ 'name' => '_dbl_http', 'arguments' => '{"x":2}' ] ],
                            ],
                        ],
                        'finish_reason' => 'tool_calls',
                    ]],
                    'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
                ]);
        })
        ->create();

    $driver = new ToolCallingDriver(
        llm: LLMProvider::using('openai'),
        httpClient: $http,
        model: 'gpt-4o-mini'
    );

    $toolUse = (new ToolUse)
        ->withDriver($driver)
        ->withMessages('Two calls')
        ->withTools([
            FunctionTool::fromCallable(_inc_http(...)),
            FunctionTool::fromCallable(_dbl_http(...)),
        ]);

    $step = $toolUse->nextStep();
    expect(count($step->toolExecutions()->all()))->toBe(2);
});

