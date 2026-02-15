<?php declare(strict_types=1);

use Cognesy\Utils\Markdown\FrontMatter;

it('returns no front matter for plain markdown', function () {
    $parsed = FrontMatter::parse("# Title\n\nBody");

    expect($parsed->hasFrontMatter())->toBeFalse();
    expect($parsed->data())->toBe([]);
    expect($parsed->document())->toBe("# Title\n\nBody");
    expect($parsed->error())->toBeNull();
});

it('parses yaml front matter and document body', function () {
    $content = <<<'MD'
---
name: demo
description: Demo agent
---
System prompt body.
MD;

    $parsed = FrontMatter::parse($content);

    expect($parsed->hasFrontMatter())->toBeTrue();
    expect($parsed->error())->toBeNull();
    expect($parsed->data()['name'])->toBe('demo');
    expect(trim($parsed->document()))->toBe('System prompt body.');
});

it('normalizes windows line endings', function () {
    $content = "---\r\nname: win\r\n---\r\nBody\r\n";

    $parsed = FrontMatter::parse($content);

    expect($parsed->hasFrontMatter())->toBeTrue();
    expect($parsed->error())->toBeNull();
    expect($parsed->data()['name'])->toBe('win');
    expect(trim($parsed->document()))->toBe('Body');
});

it('captures yaml parse errors', function () {
    $content = <<<'MD'
---
name: [unclosed
---
Body
MD;

    $parsed = FrontMatter::parse($content);

    expect($parsed->hasFrontMatter())->toBeTrue();
    expect($parsed->data())->toBe([]);
    expect($parsed->error())->not->toBeNull();
});

it('captures non-map front matter as error', function () {
    $content = <<<'MD'
---
- one
- two
---
Body
MD;

    $parsed = FrontMatter::parse($content);

    expect($parsed->hasFrontMatter())->toBeTrue();
    expect($parsed->data())->toBe([]);
    expect($parsed->error())->toBe('Front matter must be a YAML map.');
});

