<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Workspace\Arena\Record\Lineage;
use Cognesy\Tell\Workspace\Arena\Record\Message as RecordMessage;
use Cognesy\Tell\Workspace\Arena\Record\Role;
use Cognesy\Tell\Workspace\Arena\Record\TextPart;
use Cognesy\Tell\Workspace\Arena\Record\ToolCall;
use Cognesy\Tell\Workspace\Arena\Record\ToolResult;
use Cognesy\Tell\Workspace\Arena\Record\Turn;
use Cognesy\Tell\Workspace\Arena\RecordCodec;
use Cognesy\Tell\Workspace\Arena\RecordException;

beforeEach(function (): void {
    global $tellTemporaryRoots;
    $tellTemporaryRoots = [];
});

it('produces committed canonical bytes and a hash for a Unicode multiline completed turn', function (): void {
    $serializer = new RecordCodec();
    $turn = tellArenaRecordGoldenTurn();
    $bytes = $serializer->encode($turn);

    expect($bytes)->toBe(rtrim((string) file_get_contents(tellArenaRecordFixture('turn-v1.json')), "\n"))
        ->and($serializer->hash($turn)->toString())->toBe('4b77a5eaef1d32c6046592ecffa191e6fedfa7954d65561bc8d7cc1abd7b570f')
        ->and($serializer->encode($serializer->decode($bytes, $serializer->hash($turn))))
        ->toBe($bytes);
});

it('keeps empty optional lineage values omitted in a second committed golden vector', function (): void {
    $serializer = new RecordCodec();
    $turn = new Turn(
        id: 'turn-005',
        lineage: new Lineage(new ObjectHash(str_repeat('a', 64))),
        messages: [new RecordMessage(Role::User, [new TextPart('Start a fresh conversation.')])],
    );
    $bytes = $serializer->encode($turn);

    expect($bytes)->toBe(rtrim((string) file_get_contents(tellArenaRecordFixture('turn-empty-optionals-v1.json')), "\n"))
        ->and($bytes)->not->toContain('"parent"')
        ->and($bytes)->not->toContain('"compactedFrom"')
        ->and($serializer->decode($bytes)->toArray())->toBe($turn->toArray());
});

it('normalizes object keys while retaining semantic list order', function (): void {
    $serializer = new RecordCodec();
    $first = new ToolCall('call-001', 'read_file', [
        'zeta' => ['second' => 2, 'first' => 1],
        'alpha' => 'first',
    ]);
    $second = new ToolCall('call-001', 'read_file', [
        'alpha' => 'first',
        'zeta' => ['first' => 1, 'second' => 2],
    ]);

    expect($serializer->encode(tellTurnWithCall($first)))
        ->toBe($serializer->encode(tellTurnWithCall($second)));
});

it('changes the object hash when canonical semantics change', function (): void {
    $serializer = new RecordCodec();
    $first = tellArenaRecordGoldenTurn();
    $second = new Turn(
        id: 'turn-0001',
        lineage: tellLineage(),
        messages: [
            new RecordMessage(Role::User, [new TextPart("Zażółć gęślą jaźń.\nPlease inspect the file.")]),
            new RecordMessage(Role::Assistant, [new TextPart('I will inspect the requested file.')]),
        ],
        toolCalls: [new ToolCall('call-001', 'read_file', ['path' => 'docs/żółć.txt'])],
        toolResults: [new ToolResult('call-001', [new TextPart("line 1\nline 2")])],
    );

    expect($serializer->hash($first)->equals($serializer->hash($second)))->toBeFalse();
});

it('rejects malformed bytes, hash mismatches, unsupported schema, invalid roles, and broken tool pairing', function (): void {
    $serializer = new RecordCodec();
    $turn = tellArenaRecordGoldenTurn();

    expect(static fn (): mixed => $serializer->decode('{not json}'))
        ->toThrow(RecordException::class)
        ->and(static fn (): mixed => $serializer->decode($serializer->encode($turn), new ObjectHash(str_repeat('0', 64))))
        ->toThrow(RecordException::class)
        ->and(static fn (): mixed => ConversationRoot::fromArray([
            'id' => 'conversation-001',
            'kind' => 'conversation',
            'messages' => [],
            'schema' => 2,
        ]))->toThrow(RecordException::class)
        ->and(static fn (): mixed => new RecordMessage(Role::User, []))
        ->toThrow(RecordException::class)
        ->and(static fn (): mixed => new Turn(
            id: 'turn-002',
            lineage: tellLineage(),
            messages: [new RecordMessage(Role::Assistant, [])],
            toolCalls: [new ToolCall('call-002', 'read_file')],
        ))->toThrow(RecordException::class);
});

it('keeps provider observations, credentials, rendering, timing, and absolute paths out of canonical records', function (): void {
    expect(static fn (): mixed => ConversationRoot::fromArray([
        'apiKey' => 'sk-not-storable',
        'id' => 'conversation-001',
        'kind' => 'conversation',
        'messages' => [],
        'schema' => 1,
    ]))->toThrow(RecordException::class)
        ->and(static fn (): mixed => ToolCall::fromArray([
            'arguments' => ['headers' => ['authorization' => 'Bearer secret']],
            'id' => 'call-003',
            'name' => 'read_file',
        ]))->toThrow(RecordException::class)
        ->and(static fn (): mixed => ToolCall::fromArray([
            'arguments' => ['path' => '/Users/example/private.txt'],
            'id' => 'call-004',
            'name' => 'read_file',
        ]))->toThrow(RecordException::class)
        ->and(static fn (): mixed => Turn::fromArray([
            'id' => 'turn-003',
            'kind' => 'turn',
            'lineage' => ['root' => str_repeat('a', 64)],
            'messages' => [['parts' => [['text' => 'hello', 'type' => 'text']], 'role' => 'user']],
            'providerResponse' => ['usage' => ['total_tokens' => 12]],
            'schema' => 1,
            'status' => 'completed',
            'toolCalls' => [],
            'toolResults' => [],
        ]))->toThrow(RecordException::class);
});

function tellArenaRecordGoldenTurn(): Turn {
    return new Turn(
        id: 'turn-0001',
        lineage: tellLineage(),
        messages: [
            new RecordMessage(Role::User, [new TextPart("Zażółć gęślą jaźń.\nPlease inspect the file.")]),
            new RecordMessage(Role::Assistant, [new TextPart('I will inspect the requested file.')]),
        ],
        toolCalls: [new ToolCall('call-001', 'read_file', [
            'options' => ['mode' => 'preview', 'limit' => 2],
            'path' => 'docs/żółć.txt',
        ])],
        toolResults: [new ToolResult('call-001', [new TextPart("line 1\nline 2")])],
    );
}

function tellTurnWithCall(ToolCall $call): Turn {
    return new Turn(
        id: 'turn-004',
        lineage: tellLineage(),
        messages: [new RecordMessage(Role::User, [new TextPart('Inspect this file.')])],
        toolCalls: [$call],
        toolResults: [new ToolResult('call-001', [new TextPart('ok')])],
    );
}

function tellLineage(): Lineage {
    return new Lineage(
        root: new ObjectHash(str_repeat('a', 64)),
        parent: new ObjectHash(str_repeat('b', 64)),
        compactedFrom: [new ObjectHash(str_repeat('c', 64))],
    );
}

function tellArenaRecordFixture(string $name): string {
    return __DIR__ . '/../Fixtures/Arena/' . $name;
}
