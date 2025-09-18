<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Exceptions\ToolExecutionException;
use Cognesy\Addons\ToolUse\Tools;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;

function _ok(int $x): int { return $x + 1; }
function _boom(): int { throw new Exception('fail'); }

it('throws on tool failure when configured', function () {
    $tools = (new Tools())
        ->withThrowOnToolFailure(true)
        ->withTool(FunctionTool::fromCallable(_boom(...)));

    $state = new ToolUseState();
    $call = new ToolCall('_boom', []);

    expect(fn() => $tools->useTool($call, $state))->toThrow(ToolExecutionException::class);
});

it('reports missing tools and canExecute returns false for unknown tools', function () {
    $tools = new Tools([ FunctionTool::fromCallable(_ok(...)) ]);
    $state = new ToolUseState();
    $toolCalls = new ToolCalls([ new ToolCall('_ok', ['x' => 1]), new ToolCall('_missing', []) ]);

    expect($tools->canExecute($toolCalls))->toBeFalse();
    expect($tools->missing($toolCalls))->toContain('_missing');
    expect($tools->nameList())->toContain('_ok');
    expect($tools->descriptionList()[0]['name'] ?? null)->toBe('_ok');
});

