<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Render\ContentPreview;

it('bounds long content without splitting multibyte characters', function (): void {
    $preview = ContentPreview::from(str_repeat('ż', 20), full: false, limit: 10);

    expect($preview->characters)->toBe(20)
        ->and($preview->truncated)->toBeTrue()
        ->and($preview->content)->toBe(str_repeat('ż', 7) . '...')
        ->and(mb_check_encoding($preview->content, 'UTF-8'))->toBeTrue();
});

it('returns full content only when explicitly requested', function (): void {
    $content = str_repeat('x', 20);
    $preview = ContentPreview::from($content, full: true, limit: 10);

    expect($preview->content)->toBe($content)
        ->and($preview->characters)->toBe(20)
        ->and($preview->truncated)->toBeFalse();
});
