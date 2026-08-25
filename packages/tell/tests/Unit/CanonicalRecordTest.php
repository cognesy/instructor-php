<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalHashMismatch;
use Cognesy\Tell\Canonical\CanonicalLineage;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalRole;
use Cognesy\Tell\Canonical\CanonicalSerializationException;
use Cognesy\Tell\Canonical\CanonicalSerializer;
use Cognesy\Tell\Canonical\CanonicalTextPart;
use Cognesy\Tell\Canonical\CanonicalToolCall;
use Cognesy\Tell\Canonical\CanonicalToolResult;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Cognesy\Tell\Canonical\CanonicalValidationException;

beforeEach(function (): void {
    global $tellTemporaryRoots;
    $tellTemporaryRoots = [];
});

it('produces committed canonical bytes and a hash for a Unicode multiline completed turn', function (): void {
    $serializer = new CanonicalSerializer;
    $turn = tellCanonicalGoldenTurn();
    $bytes = $serializer->encode($turn);

    expect($bytes)->toBe(rtrim((string) file_get_contents(tellCanonicalFixture('turn-v1.json')), "\n"))
        ->and($serializer->hash($turn)->toString())->toBe('4b77a5eaef1d32c6046592ecffa191e6fedfa7954d65561bc8d7cc1abd7b570f')
        ->and($serializer->encode($serializer->decode($bytes, $serializer->hash($turn))))
        ->toBe($bytes);
});

it('keeps empty optional lineage values omitted in a second committed golden vector', function (): void {
    $serializer = new CanonicalSerializer;
    $turn = new CanonicalTurn(
        id: 'turn-005',
        lineage: new CanonicalLineage(new CanonicalHash(str_repeat('a', 64))),
        messages: [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('Start a fresh conversation.')])],
    );
    $bytes = $serializer->encode($turn);

    expect($bytes)->toBe(rtrim((string) file_get_contents(tellCanonicalFixture('turn-empty-optionals-v1.json')), "\n"))
        ->and($bytes)->not->toContain('"parent"')
        ->and($bytes)->not->toContain('"compactedFrom"')
        ->and($serializer->decode($bytes)->toCanonicalArray())->toBe($turn->toCanonicalArray());
});

it('normalizes object keys while retaining semantic list order', function (): void {
    $serializer = new CanonicalSerializer;
    $first = new CanonicalToolCall('call-001', 'read_file', [
        'zeta' => ['second' => 2, 'first' => 1],
        'alpha' => 'first',
    ]);
    $second = new CanonicalToolCall('call-001', 'read_file', [
        'alpha' => 'first',
        'zeta' => ['first' => 1, 'second' => 2],
    ]);

    expect($serializer->encode(tellCanonicalTurnWithCall($first)))
        ->toBe($serializer->encode(tellCanonicalTurnWithCall($second)));
});

it('changes the object hash when canonical semantics change', function (): void {
    $serializer = new CanonicalSerializer;
    $first = tellCanonicalGoldenTurn();
    $second = new CanonicalTurn(
        id: 'turn-0001',
        lineage: tellCanonicalLineage(),
        messages: [
            new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart("Zażółć gęślą jaźń.\nPlease inspect the file.")]),
            new CanonicalMessage(CanonicalRole::Assistant, [new CanonicalTextPart('I will inspect the requested file.')]),
        ],
        toolCalls: [new CanonicalToolCall('call-001', 'read_file', ['path' => 'docs/żółć.txt'])],
        toolResults: [new CanonicalToolResult('call-001', [new CanonicalTextPart("line 1\nline 2")])],
    );

    expect($serializer->hash($first)->equals($serializer->hash($second)))->toBeFalse();
});

it('rejects malformed bytes, hash mismatches, unsupported schema, invalid roles, and broken tool pairing', function (): void {
    $serializer = new CanonicalSerializer;
    $turn = tellCanonicalGoldenTurn();

    expect(static fn (): mixed => $serializer->decode('{not json}'))
        ->toThrow(CanonicalSerializationException::class)
        ->and(static fn (): mixed => $serializer->decode($serializer->encode($turn), new CanonicalHash(str_repeat('0', 64))))
        ->toThrow(CanonicalHashMismatch::class)
        ->and(static fn (): mixed => CanonicalConversationRoot::fromArray([
            'id' => 'conversation-001',
            'kind' => 'conversation',
            'messages' => [],
            'schema' => 2,
        ]))->toThrow(CanonicalValidationException::class)
        ->and(static fn (): mixed => new CanonicalMessage(CanonicalRole::User, []))
        ->toThrow(CanonicalValidationException::class)
        ->and(static fn (): mixed => new CanonicalTurn(
            id: 'turn-002',
            lineage: tellCanonicalLineage(),
            messages: [new CanonicalMessage(CanonicalRole::Assistant, [])],
            toolCalls: [new CanonicalToolCall('call-002', 'read_file')],
        ))->toThrow(CanonicalValidationException::class);
});

it('keeps provider observations, credentials, rendering, timing, and absolute paths out of canonical records', function (): void {
    expect(static fn (): mixed => CanonicalConversationRoot::fromArray([
        'apiKey' => 'sk-not-storable',
        'id' => 'conversation-001',
        'kind' => 'conversation',
        'messages' => [],
        'schema' => 1,
    ]))->toThrow(CanonicalValidationException::class)
        ->and(static fn (): mixed => CanonicalToolCall::fromArray([
            'arguments' => ['headers' => ['authorization' => 'Bearer secret']],
            'id' => 'call-003',
            'name' => 'read_file',
        ]))->toThrow(CanonicalValidationException::class)
        ->and(static fn (): mixed => CanonicalToolCall::fromArray([
            'arguments' => ['path' => '/Users/example/private.txt'],
            'id' => 'call-004',
            'name' => 'read_file',
        ]))->toThrow(CanonicalValidationException::class)
        ->and(static fn (): mixed => CanonicalTurn::fromArray([
            'id' => 'turn-003',
            'kind' => 'turn',
            'lineage' => ['root' => str_repeat('a', 64)],
            'messages' => [['parts' => [['text' => 'hello', 'type' => 'text']], 'role' => 'user']],
            'providerResponse' => ['usage' => ['total_tokens' => 12]],
            'schema' => 1,
            'status' => 'completed',
            'toolCalls' => [],
            'toolResults' => [],
        ]))->toThrow(CanonicalValidationException::class);
});

function tellCanonicalGoldenTurn(): CanonicalTurn
{
    return new CanonicalTurn(
        id: 'turn-0001',
        lineage: tellCanonicalLineage(),
        messages: [
            new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart("Zażółć gęślą jaźń.\nPlease inspect the file.")]),
            new CanonicalMessage(CanonicalRole::Assistant, [new CanonicalTextPart('I will inspect the requested file.')]),
        ],
        toolCalls: [new CanonicalToolCall('call-001', 'read_file', [
            'options' => ['mode' => 'preview', 'limit' => 2],
            'path' => 'docs/żółć.txt',
        ])],
        toolResults: [new CanonicalToolResult('call-001', [new CanonicalTextPart("line 1\nline 2")])],
    );
}

function tellCanonicalTurnWithCall(CanonicalToolCall $call): CanonicalTurn
{
    return new CanonicalTurn(
        id: 'turn-004',
        lineage: tellCanonicalLineage(),
        messages: [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('Inspect this file.')])],
        toolCalls: [$call],
        toolResults: [new CanonicalToolResult('call-001', [new CanonicalTextPart('ok')])],
    );
}

function tellCanonicalLineage(): CanonicalLineage
{
    return new CanonicalLineage(
        root: new CanonicalHash(str_repeat('a', 64)),
        parent: new CanonicalHash(str_repeat('b', 64)),
        compactedFrom: [new CanonicalHash(str_repeat('c', 64))],
    );
}

function tellCanonicalFixture(string $name): string
{
    return __DIR__.'/../Fixtures/Canonical/'.$name;
}
