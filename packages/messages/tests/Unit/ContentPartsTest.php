<?php

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;

describe('ContentParts', function () {
    it('creates empty collection', function () {
        $parts = ContentParts::empty();
        expect($parts->count())->toBe(0)
            ->and($parts->isEmpty())->toBeTrue();
    });

    it('creates from array of parts', function () {
        $parts = ContentParts::fromArray([
            ContentPart::text('Hello'),
            ['type' => 'text', 'text' => 'World'],
        ]);

        expect($parts->count())->toBe(2)
            ->and($parts->first()?->toString())->toBe('Hello')
            ->and($parts->last()?->toString())->toBe('World');
    });

    it('creates from array of strings', function () {
        $parts = ContentParts::fromArray(['Hello', 'World']);

        expect($parts->count())->toBe(2)
            ->and($parts->first()?->toString())->toBe('Hello')
            ->and($parts->last()?->toString())->toBe('World');
    });

    it('adds content parts immutably', function () {
        $parts = ContentParts::empty();
        $next = $parts->add(ContentPart::text('Hello'));

        expect($parts->count())->toBe(0)
            ->and($next->count())->toBe(1);
    });

    it('gets part by index', function () {
        $parts = ContentParts::fromArray([
            ContentPart::text('Hello'),
            ContentPart::text('World'),
        ]);

        expect($parts->get(0)?->toString())->toBe('Hello')
            ->and($parts->get(1)?->toString())->toBe('World')
            ->and($parts->get(2))->toBeNull();
    });

    it('replaces last part immutably', function () {
        $parts = ContentParts::fromArray([
            ContentPart::text('Hello'),
            ContentPart::text('World'),
        ]);
        $next = $parts->replaceLast(ContentPart::text('Done'));

        expect($parts->last()?->toString())->toBe('World')
            ->and($next->last()?->toString())->toBe('Done');
    });

    it('converts to array', function () {
        $parts = ContentParts::fromArray([
            ContentPart::text('Hello'),
            ContentPart::text('World'),
        ]);

        expect($parts->toArray())->toBe([
            ['type' => 'text', 'text' => 'Hello'],
            ['type' => 'text', 'text' => 'World'],
        ]);
    });

    it('converts to string', function () {
        $parts = ContentParts::fromArray([
            ContentPart::text('Hello'),
            new ContentPart('text', ['text' => '']),
            ContentPart::text('World'),
        ]);

        expect($parts->toString())->toBe("Hello\nWorld");
    });

    it('filters out empty parts', function () {
        $parts = ContentParts::fromArray([
            ContentPart::text('Hello'),
            new ContentPart('text', ['text' => '']),
            ContentPart::text('World'),
        ]);

        $filtered = $parts->withoutEmpty();

        expect($filtered->count())->toBe(2)
            ->and($filtered->toArray())->toBe([
                ['type' => 'text', 'text' => 'Hello'],
                ['type' => 'text', 'text' => 'World'],
            ]);
    });
});
