<?php declare(strict_types=1);

use Cognesy\Config\Dsn;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

it('rejects preset selector in inference DSN payload', function () {
    $raw = Dsn::fromString('preset=openai,model=gpt-4o-mini')->toArray();

    expect(fn() => LLMConfig::fromArray($raw))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejects connection selector in inference DSN payload', function () {
    $raw = Dsn::fromString('connection=openai,model=gpt-4o-mini')->toArray();

    expect(fn() => LLMConfig::fromArray($raw))
        ->toThrow(\InvalidArgumentException::class);
});
