<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\Bridge\ClaudeCodeBridge;
use Cognesy\AgentCtrl\Bridge\CodexBridge;
use Cognesy\AgentCtrl\Bridge\GeminiBridge;
use Cognesy\AgentCtrl\Bridge\OpenCodeBridge;
use Cognesy\AgentCtrl\Bridge\PiBridge;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Event;
use Cognesy\Logging\Config\EventLogConfig;
use Cognesy\Logging\EventLog;
use Psr\Log\LogLevel;

/**
 * Lives in Integration because opt-in logging is proven by what reaches the file.
 *
 * Direct bridge construction used to fall back to `new EventDispatcher()`, which
 * silently dropped the JSONL wiretap that the builder path gets. These pin the two
 * halves of the contract: no-events default is wiretapped, explicit injection is not.
 */

/** Reads the private $events bus the bridge built for itself. */
function bridgeBus(object $bridge): object
{
    return (new ReflectionProperty($bridge, 'events'))->getValue($bridge);
}

beforeEach(function () {
    $this->path = tempnam(sys_get_temp_dir(), 'bridge-eventlog-');
});

afterEach(function () {
    EventLog::disable();
    if (is_file($this->path)) {
        unlink($this->path);
    }
});

dataset('bridges', [
    'codex' => [fn() => new CodexBridge(), 'agent-ctrl.codex-bridge'],
    'claude-code' => [fn() => new ClaudeCodeBridge(), 'agent-ctrl.claude-code-bridge'],
    'opencode' => [fn() => new OpenCodeBridge(), 'agent-ctrl.opencode-bridge'],
    'gemini' => [fn() => new GeminiBridge(), 'agent-ctrl.gemini-bridge'],
    'pi' => [fn() => new PiBridge(), 'agent-ctrl.pi-bridge'],
]);

it('attaches the opt-in JSONL wiretap on direct construction', function (Closure $make, string $component) {
    EventLog::enable(new EventLogConfig(path: $this->path));

    bridgeBus($make())->dispatch(new BridgeWiringTestEvent(['requestId' => 'req-1']));

    $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    expect($lines)->toHaveCount(1);
    $entry = json_decode($lines[0], true, flags: JSON_THROW_ON_ERROR);
    expect($entry['channel'])->toBe($component)
        ->and($entry['context']['component'])->toBe($component);
})->with('bridges');

it('does not attach a file sink when logging is not enabled', function (Closure $make, string $component) {
    bridgeBus($make())->dispatch(new BridgeWiringTestEvent(['requestId' => 'req-1']));

    expect(trim((string) file_get_contents($this->path)))->toBe('');
})->with('bridges');

it('leaves an explicitly injected event bus untouched', function () {
    EventLog::enable(new EventLogConfig(path: $this->path));

    $injected = new EventDispatcher('custom');
    $bridge = new CodexBridge(events: $injected);

    expect(bridgeBus($bridge))->toBe($injected);

    bridgeBus($bridge)->dispatch(new BridgeWiringTestEvent(['requestId' => 'req-1']));

    // the caller's bus has no wiretap of ours, so nothing should be written
    expect(trim((string) file_get_contents($this->path)))->toBe('');
});

class BridgeWiringTestEvent extends Event
{
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->logLevel = LogLevel::INFO;
    }

    public function name(): string
    {
        return 'BridgeWiringTestEvent';
    }
}
