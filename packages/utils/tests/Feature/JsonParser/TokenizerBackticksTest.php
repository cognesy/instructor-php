<?php declare(strict_types=1);

use Cognesy\Utils\Json\Partial\TokenType;
use Cognesy\Utils\Json\Partial\TolerantTokenizer;

uses()->group('tokenizer');

function tokenizeString(string $s): array {
    $t = new TolerantTokenizer($s);
    $out = [];
    while ($tok = $t->next()) { $out[] = $tok; }
    return $out;
}

test('triple backticks toggle literal mode', function () {
    $ts = tokenizeString('"normal ```literal mode``` back to normal"');
    expect($ts[0]->type)->toBe(TokenType::String);
    expect($ts[0]->value)->toBe('normal ```literal mode``` back to normal');
});

test('quotes inside backticks are preserved literally', function () {
    $ts = tokenizeString('"SELECT * FROM ```some weird "quoted" stuff``` LIMIT 1"');
    expect($ts[0]->type)->toBe(TokenType::String);
    expect($ts[0]->value)->toBe('SELECT * FROM ```some weird "quoted" stuff``` LIMIT 1');
});

test('escapes inside backticks are preserved literally', function () {
    $ts = tokenizeString('"prefix ```literal \\n \\t \\" content``` suffix"');
    expect($ts[0]->type)->toBe(TokenType::String);
    expect($ts[0]->value)->toBe('prefix ```literal \\n \\t \\" content``` suffix');
});

test('nested backticks work correctly', function () {
    $ts = tokenizeString('"outer ```inner ` single `` double ``` outer"');
    expect($ts[0]->type)->toBe(TokenType::String);
    expect($ts[0]->value)->toBe('outer ```inner ` single `` double ``` outer');
});

test('multiple triple backtick sections in one string', function () {
    $ts = tokenizeString('"start ```first``` middle ```second``` end"');
    expect($ts[0]->type)->toBe(TokenType::String);
    expect($ts[0]->value)->toBe('start ```first``` middle ```second``` end');
});

test('unterminated backticks in partial string', function () {
    $ts = tokenizeString('"incomplete ```still literal mode');
    expect($ts[0]->type)->toBe(TokenType::StringPartial);
    expect($ts[0]->value)->toBe('incomplete ```still literal mode');
});

test('backtick counting resets on non-backtick characters', function () {
    $ts = tokenizeString('"two backticks ``x then one more ` not literal"');
    expect($ts[0]->type)->toBe(TokenType::String);
    expect($ts[0]->value)->toBe('two backticks ``x then one more ` not literal');
});

test('empty backtick literal section', function () {
    $ts = tokenizeString('"before ``````after"');
    expect($ts[0]->type)->toBe(TokenType::String);
    expect($ts[0]->value)->toBe('before ``````after');
});

test('newlines preserved in backtick literal mode', function () {
    $ts = tokenizeString('"start ```line1\nline2\nline3``` end"');
    expect($ts[0]->type)->toBe(TokenType::String);
    expect($ts[0]->value)->toBe('start ```line1\nline2\nline3``` end');
});

test('backticks without triple count are treated normally', function () {
    $ts = tokenizeString('"single ` backtick and double `` backticks"');
    expect($ts[0]->type)->toBe(TokenType::String);
    expect($ts[0]->value)->toBe('single ` backtick and double `` backticks');
});

test('complex SQL example with backticks', function () {
    $input = '{"query": "SELECT name FROM users WHERE data = ```{"key": "value", "nested": "quotes"}``` ORDER BY id"}';
    $ts = tokenizeString($input);
    
    // Should tokenize as: { "query" : "..." }
    expect($ts[0]->type)->toBe(TokenType::LeftBrace);
    expect($ts[1]->type)->toBe(TokenType::String);
    expect($ts[1]->value)->toBe('query');
    expect($ts[2]->type)->toBe(TokenType::Colon);
    expect($ts[3]->type)->toBe(TokenType::String);
    expect($ts[3]->value)->toBe('SELECT name FROM users WHERE data = ```{"key": "value", "nested": "quotes"}``` ORDER BY id');
    expect($ts[4]->type)->toBe(TokenType::RightBrace);
});