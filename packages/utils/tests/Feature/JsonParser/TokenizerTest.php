<?php declare(strict_types=1);

use Cognesy\Utils\Json\Partial\TokenType;
use Cognesy\Utils\Json\Partial\TolerantTokenizer;

uses()->group('tokenizer');

function tokens(string $s): array {
    $t = new TolerantTokenizer($s);
    $out = [];
    while ($tok = $t->next()) { $out[] = $tok; }
    return $out;
}

test('structural tokens are emitted as enum types', function () {
    $ts = tokens('{ } [ ] : ,');
    expect(array_map(fn($t) => $t->type, $ts))->toEqual([
        TokenType::LeftBrace,
        TokenType::RightBrace,
        TokenType::LeftBracket,
        TokenType::RightBracket,
        TokenType::Colon,
        TokenType::Comma,
    ]);
});

test('string tokens: complete and partial', function () {
    $ts = tokens('"abc" "unterminated');
    expect($ts[0]->type)->toBe(TokenType::String);
    expect($ts[0]->value)->toBe('abc');
    expect($ts[1]->type)->toBe(TokenType::StringPartial);
    expect($ts[1]->value)->toBe('unterminated');
});

test('number tokens: complete and partial', function () {
    $ts = tokens('123 -4.5 6.7e+2 89foo');
    expect($ts[0]->type)
        ->toBe(TokenType::Number)
        ->and($ts[0]->value)->toBe('123')
        ->and($ts[1]->type)->toBe(TokenType::Number)
        ->and($ts[1]->value)->toBe('-4.5')
        ->and($ts[2]->type)->toBe(TokenType::Number)
        ->and($ts[2]->value)->toBe('6.7e+2')
        ->and($ts[3]->type)->toBe(TokenType::NumberPartial)
        ->and($ts[3]->value)->toBe('89')
        ->and($ts[4]->type)->toBe(TokenType::String)
        ->and($ts[4]->value)->toBe('foo');

    // "89foo" → NUMBER_PARTIAL('89') then STRING('foo')
});

test('true/false/null tolerant prefixes', function () {
    $ts = tokens('true false null');
    expect([$ts[0]->type, $ts[1]->type, $ts[2]->type])->toEqual([
        TokenType::True, TokenType::False, TokenType::Null
    ]);
});

test('barewords become STRING tokens', function () {
    $ts = tokens('foo,bar}');
    expect($ts[0]->type)->toBe(TokenType::String);
    expect($ts[0]->value)->toBe('foo');
    expect($ts[1]->type)->toBe(TokenType::Comma);
    expect($ts[2]->value)->toBe('bar');
    expect($ts[3]->type)->toBe(TokenType::RightBrace);
});
