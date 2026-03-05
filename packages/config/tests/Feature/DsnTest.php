<?php declare(strict_types=1);

use Cognesy\Config\Dsn;
use Cognesy\Config\DsnParser;

it('parses key value pairs to a flat array', function () {
    $dsn = Dsn::fromString('driver=openai,model=gpt-4o-mini,temperature=0.2');

    expect($dsn->toArray())->toBe([
        'driver' => 'openai',
        'model' => 'gpt-4o-mini',
        'temperature' => '0.2',
    ]);
});

it('parses dot notation into nested arrays', function () {
    $dsn = Dsn::fromString('metadata.apiVersion=2024-08-01,metadata.region=us-east-1');

    expect($dsn->toArray())->toBe([
        'metadata' => [
            'apiVersion' => '2024-08-01',
            'region' => 'us-east-1',
        ],
    ]);
    expect($dsn->param('metadata.apiVersion'))->toBe('2024-08-01');
});

it('ignores malformed segments without key-value separator', function () {
    $dsn = Dsn::fromString('driver=openai,malformed,model=gpt-4o-mini');

    expect($dsn->toArray())->toBe([
        'driver' => 'openai',
        'model' => 'gpt-4o-mini',
    ]);
});

it('does not treat selector-like keys specially', function () {
    $dsn = Dsn::fromString('preset=openai,connection=primary,profile=fast');

    expect($dsn->toArray())->toBe([
        'preset' => 'openai',
        'connection' => 'primary',
        'profile' => 'fast',
    ]);
});

it('keeps template-like tokens as plain strings', function () {
    $dsn = Dsn::fromString('apiKey={OPENAI_API_KEY}');

    expect($dsn->toArray())->toBe([
        'apiKey' => '{OPENAI_API_KEY}',
    ]);
});

it('supports typed accessors while preserving raw parsed values', function () {
    $dsn = Dsn::fromString('i=10,f=1.5,b=true,s=text');

    expect($dsn->intParam('i'))->toBe(10);
    expect($dsn->floatParam('f'))->toBe(1.5);
    expect($dsn->boolParam('b'))->toBeTrue();
    expect($dsn->stringParam('s'))->toBe('text');
    expect($dsn->toArray())->toBe([
        'i' => '10',
        'f' => '1.5',
        'b' => 'true',
        's' => 'text',
    ]);
});

it('provides parser-level DSN check and nullable constructor', function () {
    $parser = new DsnParser();

    expect($parser->isDsn('driver=openai'))->toBeTrue();
    expect($parser->isDsn('not-a-dsn'))->toBeFalse();
    expect(Dsn::ifValid('not-a-dsn'))->toBeNull();
    expect(Dsn::fromString(null)->toArray())->toBe([]);
});

