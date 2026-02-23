<?php declare(strict_types=1);

use Cognesy\AgentCtrl\OpenCode\Domain\Dto\StreamEvent\ToolUseEvent;

it('normalizes array output to json string', function () {
    $event = ToolUseEvent::fromArray([
        'timestamp' => 123,
        'sessionID' => 'session_1',
        'part' => [
            'messageID' => 'message_1',
            'id' => 'part_1',
            'callID' => 'call_1',
            'tool' => 'bash',
            'state' => [
                'status' => 'completed',
                'input' => ['command' => 'ls -la'],
                'output' => ['stdout' => 'ok', 'exitCode' => 0],
            ],
        ],
    ]);

    expect($event->output)->toBe('{"stdout":"ok","exitCode":0}');
});

it('normalizes object output to json string', function () {
    $output = (object) ['status' => 'ok'];

    $event = ToolUseEvent::fromArray([
        'timestamp' => 123,
        'sessionID' => 'session_1',
        'part' => [
            'messageID' => 'message_1',
            'id' => 'part_1',
            'callID' => 'call_1',
            'tool' => 'bash',
            'state' => [
                'status' => 'completed',
                'input' => ['command' => 'ls -la'],
                'output' => $output,
            ],
        ],
    ]);

    expect($event->output)->toBe('{"status":"ok"}');
});

it('normalizes scalar outputs without type errors', function () {
    $event = ToolUseEvent::fromArray([
        'timestamp' => 123,
        'sessionID' => 'session_1',
        'part' => [
            'messageID' => 'message_1',
            'id' => 'part_1',
            'callID' => 'call_1',
            'tool' => 'bash',
            'state' => [
                'status' => 'completed',
                'input' => ['command' => 'ls -la'],
                'output' => true,
            ],
        ],
    ]);

    expect($event->output)->toBe('true');
});
