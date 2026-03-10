<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Gemini\Application\Parser\ResponseParser;
use Cognesy\Sandbox\Data\ExecResult;

function makeGeminiExecResult(string $stdout, int $exitCode = 0): ExecResult
{
    return new ExecResult($stdout, '', $exitCode, 0.1);
}

it('parses realistic gemini stream-json output', function () {
    $jsonl = implode("\n", [
        '{"type":"init","timestamp":"2025-01-01T00:00:00Z","session_id":"sess-abc-123","model":"gemini-2.5-pro"}',
        '{"type":"message","timestamp":"2025-01-01T00:00:00Z","role":"user","content":"What is 2+2?","delta":false}',
        '{"type":"message","timestamp":"2025-01-01T00:00:01Z","role":"assistant","content":"The answer ","delta":true}',
        '{"type":"message","timestamp":"2025-01-01T00:00:01Z","role":"assistant","content":"is 4.","delta":true}',
        '{"type":"result","timestamp":"2025-01-01T00:00:02Z","status":"success","stats":{"total_tokens":50,"input_tokens":20,"output_tokens":30,"cached":5,"duration_ms":1500,"tool_calls":0}}',
    ]);

    $parser = new ResponseParser(failFast: false);
    $response = $parser->parse(makeGeminiExecResult($jsonl));

    expect($response->sessionId())->not->toBeNull()
        ->and($response->sessionId()->toString())->toBe('sess-abc-123')
        ->and($response->messageText())->toBe('The answer is 4.')
        ->and($response->usage())->not->toBeNull()
        ->and($response->usage()->input)->toBe(20)
        ->and($response->usage()->output)->toBe(30)
        ->and($response->usage()->cached)->toBe(5)
        ->and($response->usage()->totalTokens)->toBe(50)
        ->and($response->toolCalls())->toBeEmpty()
        ->and($response->parseFailures())->toBe(0);
});

it('parses gemini output with tool use and result', function () {
    $jsonl = implode("\n", [
        '{"type":"init","timestamp":"2025-01-01T00:00:00Z","session_id":"sess-xyz","model":"gemini-2.5-flash"}',
        '{"type":"message","timestamp":"2025-01-01T00:00:01Z","role":"assistant","content":"Let me read that file.","delta":true}',
        '{"type":"tool_use","timestamp":"2025-01-01T00:00:02Z","tool_name":"read_file","tool_id":"call_001","parameters":{"path":"composer.json"}}',
        '{"type":"tool_result","timestamp":"2025-01-01T00:00:03Z","tool_id":"call_001","status":"success","output":"{\"name\": \"my-project\"}"}',
        '{"type":"message","timestamp":"2025-01-01T00:00:04Z","role":"assistant","content":" The project is called my-project.","delta":true}',
        '{"type":"result","timestamp":"2025-01-01T00:00:05Z","status":"success","stats":{"total_tokens":100,"input_tokens":40,"output_tokens":60,"cached":0,"duration_ms":3000,"tool_calls":1}}',
    ]);

    $parser = new ResponseParser(failFast: false);
    $response = $parser->parse(makeGeminiExecResult($jsonl));

    expect($response->messageText())->toBe('Let me read that file. The project is called my-project.')
        ->and($response->toolCalls())->toHaveCount(1)
        ->and($response->toolCalls()[0]['tool'])->toBe('read_file')
        ->and($response->toolCalls()[0]['input'])->toBe(['path' => 'composer.json'])
        ->and($response->toolCalls()[0]['output'])->toBe('{"name": "my-project"}')
        ->and($response->toolCalls()[0]['isError'])->toBeFalse()
        ->and($response->toolCalls()[0]['toolId'])->toBe('call_001');
});

it('pairs tool_result with tool_use by tool_id', function () {
    $jsonl = implode("\n", [
        '{"type":"init","timestamp":"2025-01-01T00:00:00Z","session_id":"sess-xyz","model":"flash"}',
        '{"type":"tool_use","timestamp":"2025-01-01T00:00:01Z","tool_name":"read_file","tool_id":"call_a","parameters":{"path":"a.txt"}}',
        '{"type":"tool_use","timestamp":"2025-01-01T00:00:01Z","tool_name":"search_files","tool_id":"call_b","parameters":{"query":"test"}}',
        '{"type":"tool_result","timestamp":"2025-01-01T00:00:02Z","tool_id":"call_b","status":"success","output":"found 3 matches"}',
        '{"type":"tool_result","timestamp":"2025-01-01T00:00:02Z","tool_id":"call_a","status":"success","output":"file contents"}',
        '{"type":"result","timestamp":"2025-01-01T00:00:03Z","status":"success","stats":{}}',
    ]);

    $parser = new ResponseParser(failFast: false);
    $response = $parser->parse(makeGeminiExecResult($jsonl));

    expect($response->toolCalls())->toHaveCount(2)
        ->and($response->toolCalls()[0]['tool'])->toBe('search_files')
        ->and($response->toolCalls()[0]['toolId'])->toBe('call_b')
        ->and($response->toolCalls()[1]['tool'])->toBe('read_file')
        ->and($response->toolCalls()[1]['toolId'])->toBe('call_a');
});

it('handles tool_result error', function () {
    $jsonl = implode("\n", [
        '{"type":"init","timestamp":"2025-01-01T00:00:00Z","session_id":"s1","model":"pro"}',
        '{"type":"tool_use","timestamp":"2025-01-01T00:00:01Z","tool_name":"write_file","tool_id":"call_err","parameters":{"path":"/etc/passwd","content":"hack"}}',
        '{"type":"tool_result","timestamp":"2025-01-01T00:00:02Z","tool_id":"call_err","status":"error","error":{"type":"permission_denied","message":"Access denied"}}',
        '{"type":"result","timestamp":"2025-01-01T00:00:03Z","status":"success","stats":{}}',
    ]);

    $parser = new ResponseParser(failFast: false);
    $response = $parser->parse(makeGeminiExecResult($jsonl));

    expect($response->toolCalls())->toHaveCount(1)
        ->and($response->toolCalls()[0]['isError'])->toBeTrue();
});

it('handles malformed json lines gracefully', function () {
    $jsonl = implode("\n", [
        '{"type":"init","session_id":"s1","model":"pro","timestamp":""}',
        'not valid json',
        '{"type":"message","role":"assistant","content":"Hello","delta":true,"timestamp":""}',
        'also broken{',
        '{"type":"result","status":"success","stats":{"input_tokens":10,"output_tokens":5},"timestamp":""}',
    ]);

    $parser = new ResponseParser(failFast: false);
    $response = $parser->parse(makeGeminiExecResult($jsonl));

    expect($response->messageText())->toBe('Hello')
        ->and($response->parseFailures())->toBe(2)
        ->and($response->parseFailureSamples())->toHaveCount(2);
});

it('falls back to raw text when no jsonl events found', function () {
    $parser = new ResponseParser(failFast: false);
    $response = $parser->parse(makeGeminiExecResult('plain text output'));

    expect($response->messageText())->toBe('plain text output')
        ->and($response->sessionId())->toBeNull()
        ->and($response->usage())->toBeNull();
});

it('handles empty output', function () {
    $parser = new ResponseParser(failFast: false);
    $response = $parser->parse(makeGeminiExecResult(''));

    expect($response->messageText())->toBe('')
        ->and($response->sessionId())->toBeNull()
        ->and($response->toolCalls())->toBeEmpty();
});
