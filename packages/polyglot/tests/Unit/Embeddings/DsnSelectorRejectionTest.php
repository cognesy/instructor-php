<?php declare(strict_types=1);

use Cognesy\Config\Dsn;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;

it('rejects preset selector in embeddings DSN payload', function () {
    $raw = Dsn::fromString('preset=openai,model=text-embedding-3-small')->toArray();

    expect(fn() => EmbeddingsConfig::fromArray($raw))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejects connection selector in embeddings DSN payload', function () {
    $raw = Dsn::fromString('connection=openai,model=text-embedding-3-small')->toArray();

    expect(fn() => EmbeddingsConfig::fromArray($raw))
        ->toThrow(\InvalidArgumentException::class);
});
