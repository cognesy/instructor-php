<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Agents\Capability\File\ReadFileTool;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Collections\RegisteredHooks;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Messages\ToolCall;
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Tell\Runtime\CanReadTellClock;
use Cognesy\Tell\Runtime\TellExecutionBudgetHook;
use Cognesy\Tell\Runtime\TellExecutionPolicy;
use Cognesy\Tell\Runtime\TellPaths;
use Cognesy\Tell\Runtime\TellSpillToolOutputHook;
use Cognesy\Tell\Runtime\ToolOutputSpill;
use Cognesy\Utils\Result\Result;

/** A project directory, and separately the blob store that serves it. */
function tellSpillProject(string $name): string
{
    tellTestFactory();
    $project = tellLastTemporaryRoot().'/'.$name;
    mkdir($project, 0700, true);

    return $project;
}

function tellSpillStore(string $project): string
{
    return tellLastTemporaryRoot().'/blobstore/'.basename($project);
}

/** The two AfterToolUse hooks, registered exactly as the agent factory does. */
function tellSpillStack(string $project, TellExecutionPolicy $policy, CanReadTellClock $clock): HookStack
{
    return (new HookStack(new RegisteredHooks))
        ->with(
            hook: new TellExecutionBudgetHook($policy, $clock),
            triggerTypes: HookTriggers::of(HookTrigger::AfterToolUse),
            priority: 300,
            name: 'tell:execution_budget',
        )
        ->with(
            hook: new TellSpillToolOutputHook(ToolOutputSpill::fromPolicy(tellSpillStore($project), $policy)),
            triggerTypes: HookTriggers::of(HookTrigger::AfterToolUse),
            priority: 400,
            name: 'tell:spill_tool_output',
        );
}

/** The blob path a stub names, without the bracket or quote that follows it. */
function tellSpillPath(string $stub, string $extension): string
{
    preg_match('/([^\s\]"]+\.'.$extension.')/', $stub, $matches);

    return $matches[1] ?? '';
}

/** How many head lines a stub previewed, according to its own read hint. */
function tellSpillShown(string $stub): int
{
    preg_match('/offset=(\d+), limit=200\)/', $stub, $matches);

    return (int) ($matches[1] ?? -1);
}

/** A result long enough to spill, whose lines are individually identifiable. */
function tellSpillText(int $lines): string
{
    $rows = [];
    for ($line = 1; $line <= $lines; $line++) {
        $rows[] = 'line '.$line.' '.str_repeat('x', 60);
    }

    return implode("\n", $rows)."\n";
}

it('stores an oversized tool result and answers with a stub that describes it', function (): void {
    $project = tellSpillProject('spill-stub');
    $text = tellSpillText(400);
    $stub = (string) (new ToolOutputSpill(tellSpillStore($project), 4_000, 1_000_000, 2_000))->replace($text);
    $shown = tellSpillShown($stub);

    expect($stub)->toStartWith('[tool output: 400 lines, ')
        ->and($stub)->toContain('— stored at '.tellSpillStore($project))
        // The head is the point: as much of it as the stub budget buys, in
        // order, and the read hint resumes exactly where the preview stopped.
        ->and($shown)->toBeGreaterThan(10)
        ->and($stub)->toContain("\n  line 1 ")
        ->and($stub)->toContain("\n  line ".$shown.' ')
        ->and($stub)->not->toContain("\n  line ".($shown + 1).' ')
        ->and(strlen($stub))->toBeLessThanOrEqual(2_000);

    $blob = tellSpillPath($stub, 'txt');
    expect($blob)->not->toBe('')
        ->and(file_get_contents($blob))->toBe($text);
});

it('spends only the stub budget on the preview, and keeps the way back at any budget', function (): void {
    $project = tellSpillProject('spill-budget');
    $text = tellSpillText(400);
    $wide = (string) (new ToolOutputSpill(tellSpillStore($project), 4_000, 1_000_000, 4_000))->replace($text);
    $narrow = (string) (new ToolOutputSpill(tellSpillStore($project), 4_000, 1_000_000, 500))->replace($text);
    $none = (string) (new ToolOutputSpill(tellSpillStore($project), 4_000, 1_000_000, 0))->replace($text);

    expect(tellSpillShown($wide))->toBeGreaterThan(tellSpillShown($narrow))
        ->and(tellSpillShown($narrow))->toBeGreaterThan(0)
        ->and(strlen($wide))->toBeLessThanOrEqual(4_000)
        ->and(strlen($narrow))->toBeLessThanOrEqual(500)
        // A budget too small for a preview still buys the header and the read
        // hint; a stub without them names a file and loses how to open it.
        ->and(tellSpillShown($none))->toBe(0)
        ->and($none)->toStartWith('[tool output: 400 lines, ')
        ->and($none)->toContain('offset=0, limit=200)')
        ->and($none)->not->toContain("\n  line 1 ");
});

it('leaves a result the model can already read where it is', function (): void {
    $project = tellSpillProject('spill-small');
    $spill = new ToolOutputSpill(tellSpillStore($project), 40_000, 1_000_000);

    expect($spill->replace(tellSpillText(10)))->toBeNull()
        ->and($spill->replace(['data' => ['text' => 'short']]))->toBeNull()
        ->and(is_dir(tellSpillStore($project)))->toBeFalse();
});

it('replaces the payload of a structured envelope without discarding the envelope', function (): void {
    $project = tellSpillProject('spill-envelope');
    $envelope = [
        'success' => true,
        'operation' => 'bash',
        'invoked_as' => 'shell',
        'data' => ['text' => tellSpillText(300)],
        'error' => null,
        'truncated' => false,
        'partial' => false,
    ];
    $replaced = (new ToolOutputSpill(tellSpillStore($project), 4_000, 1_000_000, 2_000))->replace($envelope);

    expect($replaced)->toBeArray()
        ->and($replaced['success'])->toBeTrue()
        ->and($replaced['operation'])->toBe('bash')
        ->and($replaced['error'])->toBeNull()
        ->and($replaced['truncated'])->toBeTrue()
        ->and($replaced['data']['text'])->toStartWith('[tool output: 300 lines, ');
});

it('writes one blob for one result, however many times it is produced', function (): void {
    $project = tellSpillProject('spill-addressed');
    $spill = new ToolOutputSpill(tellSpillStore($project), 4_000, 1_000_000, 2_000);
    $text = tellSpillText(200);

    expect($spill->replace($text))->toBe($spill->replace($text))
        ->and(glob(tellSpillStore($project).'/*.txt'))->toHaveCount(1)
        // Nothing is written into the project the output came from.
        ->and(is_dir($project.'/.tell'))->toBeFalse();
});

it('emits the stub whole, however small the retained-bytes limit is', function (): void {
    $project = tellSpillProject('spill-whole-stub');
    // The limit governs tool results, and the stub is the answer to it: a stub
    // cut short would name a blob and then lose the way to read it.
    $policy = new TellExecutionPolicy(maxToolOutputChars: 300, maxSpillBytes: 1_000_000);
    $clock = new class implements CanReadTellClock
    {
        public function nowMs(): int
        {
            return 0;
        }
    };
    $now = new DateTimeImmutable;
    $call = ToolCall::fromArray(['name' => 'shell', 'arguments' => []]);
    $execution = new ToolExecution($call, Result::success(tellSpillText(400)), $now, $now);
    $value = (string) tellSpillStack($project, $policy, $clock)
        ->intercept(HookContext::afterToolUse(AgentState::empty(), $execution))
        ->toolExecution()?->value();

    expect(strlen($value))->toBeGreaterThan(300)
        ->and($value)->toStartWith('[tool output: 400 lines, ')
        ->and(tellSpillShown($value))->toBeGreaterThan(10)
        ->and($value)->not->toContain('… [truncated]');
});

it('stores binary output without previewing it or promising a read', function (): void {
    $project = tellSpillProject('spill-binary');
    // A NUL byte is what makes `file` call something binary, and the read tool
    // refuses whatever `file` calls binary.
    $binary = str_repeat("\x89PNG\r\n\x1a\n\0\0\0\rIHDR\xff\xfe", 400);
    $stub = (string) (new ToolOutputSpill(tellSpillStore($project), 1_000, 200_000, 2_000))->replace($binary);

    expect($stub)->toStartWith('[tool output: ')
        ->and($stub)->toContain('of binary data — stored at '.tellSpillStore($project))
        // The extension does not claim to be text, and no read is suggested.
        ->and($stub)->toContain('.bin]')
        ->and($stub)->not->toContain('Continue: read(')
        ->and($stub)->toContain('the read tool will not open it')
        // None of the bytes themselves reach the conversation.
        ->and($stub)->not->toContain("\0")
        ->and($stub)->not->toContain('IHDR');

    $blob = tellSpillPath($stub, 'bin');
    expect(file_get_contents($blob))->toBe($binary);
});

it('does not walk a binary result byte by byte looking for a character boundary', function (): void {
    $project = tellSpillProject('spill-binary-ceiling');
    $binary = random_bytes(40_000)."\0".random_bytes(40_000);
    $stub = (string) (new ToolOutputSpill(tellSpillStore($project), 1_000, 20_000, 2_000))->replace($binary);

    $blob = tellSpillPath($stub, 'bin');
    $stored = (string) file_get_contents($blob);

    // Backing off to a UTF-8 boundary would have eaten the whole prefix.
    expect(strlen($stored))->toBe(20_000)
        ->and($stored)->toBe(substr($binary, 0, 20_000))
        ->and($stub)->toContain('was discarded]');
});

it('stops at the spill ceiling, on a character boundary, and says so', function (): void {
    $project = tellSpillProject('spill-ceiling');
    $text = str_repeat('zażółć gęślą jaźń ', 2_000);
    $stub = (new ToolOutputSpill(tellSpillStore($project), 1_000, 5_000))->replace($text);

    expect($stub)->toContain('was discarded]');
    $blob = tellSpillPath((string) $stub, 'txt');
    $stored = (string) file_get_contents($blob);
    expect(strlen($stored))->toBeLessThanOrEqual(5_000)
        ->and(preg_match('//u', $stored))->toBe(1)
        ->and($stored)->toBe(substr($text, 0, strlen($stored)));
});

it('writes nothing at all when the spill ceiling is zero', function (): void {
    $project = tellSpillProject('spill-off');
    $policy = new TellExecutionPolicy(maxToolOutputChars: 1_000, maxSpillBytes: 0);

    expect($policy->spillsToolOutput())->toBeFalse()
        ->and(ToolOutputSpill::fromPolicy(tellSpillStore($project), $policy)->replace(tellSpillText(400)))->toBeNull()
        ->and(is_dir(tellSpillStore($project)))->toBeFalse();
});

it('spills before the budget hook can truncate the bytes worth keeping', function (): void {
    $project = tellSpillProject('spill-ordering');
    $policy = new TellExecutionPolicy(maxToolOutputChars: 4_000, maxSpillBytes: 1_000_000);
    $clock = new class implements CanReadTellClock
    {
        public function nowMs(): int
        {
            return 0;
        }
    };
    $stack = tellSpillStack($project, $policy, $clock);

    $now = new DateTimeImmutable;
    $call = ToolCall::fromArray(['name' => 'shell', 'arguments' => []]);
    $execution = new ToolExecution($call, Result::success(tellSpillText(400)), $now, $now);
    $value = (string) $stack
        ->intercept(HookContext::afterToolUse(AgentState::empty(), $execution))
        ->toolExecution()?->value();

    expect($value)->toStartWith('[tool output: 400 lines, ')
        ->and($value)->not->toContain('… [truncated]');
});

it('hands the model a path its own read tool can open', function (): void {
    $project = tellSpillProject('spill-readable');
    $stub = (string) (new ToolOutputSpill(tellSpillStore($project), 4_000, 1_000_000, 2_000))->replace(tellSpillText(400));
    $blob = tellSpillPath($stub, 'txt');

    $read = ReadFileTool::fromPolicy(
        ExecutionPolicy::in($project)
            ->withTimeout(30)
            ->withReadablePaths($project, tellSpillStore($project))
            ->inheritEnvironment(),
        'read',
    )(path: $blob, offset: 20, limit: 5);

    expect($read)->toContain('line 21 ')
        ->and($read)->toContain('line 25 ')
        ->and($read)->not->toContain('Error:');
});

it('writes nothing into the project it spilled from, workspace or not', function (): void {
    $project = tellSpillProject('spill-clean-project');
    $home = tellLastTemporaryRoot().'/tell-home';
    $paths = new TellPaths($home.'/package-agents', $home);
    // The store the agent factory would hand the hook for a turn in this
    // directory - stateless turns included, which have no workspace to write to
    // and must leave the directory exactly as they found it.
    $store = $paths->blobsFor($project);

    $stub = (string) (new ToolOutputSpill($store, 4_000, 1_000_000, 2_000))->replace(tellSpillText(400));
    $blob = tellSpillPath($stub, 'txt');

    expect(scandir($project))->toBe(['.', '..'])
        ->and($blob)->toStartWith($store.DIRECTORY_SEPARATOR)
        ->and(is_file($blob))->toBeTrue()
        ->and(substr(sprintf('%o', fileperms($store)), -3))->toBe('700');
});
