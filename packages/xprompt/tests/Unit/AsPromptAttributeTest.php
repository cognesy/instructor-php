<?php

declare(strict_types=1);

use Cognesy\Xprompt\Attributes\AsPrompt;

it('stores the prompt name', function () {
    $attr = new AsPrompt('reviewer.analyze');
    expect($attr->name)->toBe('reviewer.analyze');
});

it('is readable via reflection', function () {
    #[AsPrompt('test.prompt')]
    class AsPromptTestFixture {}

    $ref = new ReflectionClass(AsPromptTestFixture::class);
    $attrs = $ref->getAttributes(AsPrompt::class);

    expect($attrs)->toHaveCount(1);

    $instance = $attrs[0]->newInstance();
    expect($instance->name)->toBe('test.prompt');
});

it('supports dotted name convention', function () {
    $attr = new AsPrompt('domain.subdomain.action');
    expect($attr->name)->toBe('domain.subdomain.action');
});
