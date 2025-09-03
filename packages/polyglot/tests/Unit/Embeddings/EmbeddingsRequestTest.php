<?php

use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;

it('normalizes inputs and stores model and options', function () {
    $req = new EmbeddingsRequest(input: 'hello', options: ['user' => 'u1'], model: 'text-embedding-3-small');
    expect($req->inputs())->toBe(['hello']);
    expect($req->model())->toBe('text-embedding-3-small');
    expect($req->options()['user'] ?? null)->toBe('u1');
});

it('accepts array inputs', function () {
    $req = new EmbeddingsRequest(input: ['a', 'b']);
    expect($req->inputs())->toBe(['a', 'b']);
});

it('throws on empty inputs', function () {
    $act = fn() => new EmbeddingsRequest(input: []);
    expect($act)->toThrow(InvalidArgumentException::class);
});

