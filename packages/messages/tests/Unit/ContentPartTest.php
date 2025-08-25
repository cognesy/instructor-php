<?php

use Cognesy\Messages\ContentPart;

describe('ContentPart', function () {
    describe('construction', function () {
        it('creates content part with type and fields', function () {
            $part = new ContentPart('text', ['text' => 'Hello']);
            expect($part->type())->toBe('text');
            expect($part->get('text'))->toBe('Hello');
        });

        it('filters out null and empty arrays during construction', function () {
            $part = new ContentPart('text', [
                'text' => 'Hello',
                'empty_string' => '',
                'null_value' => null,
                'empty_array' => [],
                'valid_field' => 'value'
            ]);
            expect($part->has('text'))->toBeTrue();
            expect($part->has('empty_string'))->toBeTrue();
            expect($part->has('null_value'))->toBeFalse();
            expect($part->has('empty_array'))->toBeFalse();
            expect($part->has('valid_field'))->toBeTrue();
        });
    });

    describe('factory methods', function () {
        it('creates from array', function () {
            $array = ['type' => 'text', 'text' => 'Hello'];
            $part = ContentPart::fromArray($array);
            expect($part->type())->toBe('text');
            expect($part->get('text'))->toBe('Hello');
        });

        it('creates from array with default text type', function () {
            $array = ['text' => 'Hello'];
            $part = ContentPart::fromArray($array);
            expect($part->type())->toBe('text');
            expect($part->get('text'))->toBe('Hello');
        });

        it('creates text part', function () {
            $part = ContentPart::text('Hello world');
            expect($part->type())->toBe('text');
            expect($part->get('text'))->toBe('Hello world');
        });

        it('creates image URL part', function () {
            $part = ContentPart::imageUrl('https://example.com/image.jpg');
            expect($part->type())->toBe('image_url');
            expect($part->get('url'))->toBe('https://example.com/image.jpg');
        });

        it('creates from string using fromAny', function () {
            $part = ContentPart::fromAny('Hello');
            expect($part->type())->toBe('text');
            expect($part->get('text'))->toBe('Hello');
        });

        it('creates from array using fromAny', function () {
            $array = ['type' => 'text', 'text' => 'Hello'];
            $part = ContentPart::fromAny($array);
            expect($part->type())->toBe('text');
            expect($part->get('text'))->toBe('Hello');
        });

        it('returns same instance using fromAny', function () {
            $original = ContentPart::text('Hello');
            $part = ContentPart::fromAny($original);
            expect($part)->toBe($original);
        });

        it('throws exception for unsupported type in fromAny', function () {
            expect(fn() => ContentPart::fromAny(123))->toThrow(InvalidArgumentException::class);
        });
    });

    describe('type checking', function () {
        it('identifies text part', function () {
            $part = ContentPart::text('Hello');
            expect($part->isTextPart())->toBeTrue();
        });

        it('identifies non-text part', function () {
            $part = ContentPart::imageUrl('https://example.com/image.jpg');
            expect($part->isTextPart())->toBeFalse();
        });

        it('detects text presence', function () {
            $part = ContentPart::text('Hello');
            expect($part->hasText())->toBeTrue();
        });

        it('detects missing text', function () {
            $part = ContentPart::imageUrl('https://example.com/image.jpg');
            expect($part->hasText())->toBeFalse();
        });
    });

    describe('field management', function () {
        it('sets and gets field values', function () {
            $part = new ContentPart('text');
            $part = $part->withField('custom', 'value');
            expect($part->get('custom'))->toBe('value');
        });

        it('returns default value for missing field', function () {
            $part = new ContentPart('text');
            expect($part->get('missing', 'default'))->toBe('default');
        });

        it('checks field existence', function () {
            $part = new ContentPart('text', ['text' => 'Hello']);
            expect($part->has('text'))->toBeTrue();
            expect($part->has('missing'))->toBeFalse();
        });

        it('replaces all fields with withFields', function () {
            $part = new ContentPart('text', ['text' => 'Hello', 'old' => 'value']);
            $part = $part->withFields(['text' => 'New', 'new' => 'field']);
            expect($part->get('text'))->toBe('New');
            expect($part->get('new'))->toBe('field');
            expect($part->has('old'))->toBeFalse();
        });
    });

    describe('state checking', function () {
        it('detects empty content part', function () {
            $part = new ContentPart('text');
            expect($part->isEmpty())->toBeTrue();
        });

        it('detects non-empty content part', function () {
            $part = ContentPart::text('Hello');
            expect($part->isEmpty())->toBeFalse();
        });

        it('detects empty with null values', function () {
            $part = new ContentPart('text', ['text' => null, 'empty' => '']);
            expect($part->isEmpty())->toBeTrue();
        });

        it('detects simple content part', function () {
            $part = ContentPart::text('Hello');
            expect($part->isSimple())->toBeTrue();
        });

        it('detects non-simple content part', function () {
            $part = new ContentPart('text', ['text' => 'Hello', 'extra' => 'field']);
            expect($part->isSimple())->toBeFalse();
        });
    });

    describe('conversion methods', function () {
        it('converts to array', function () {
            $part = ContentPart::text('Hello');
            $array = $part->toArray();
            expect($array)->toHaveKey('type', 'text');
            expect($array)->toHaveKey('text', 'Hello');
        });

        it('excludes empty values from array', function () {
            $part = new ContentPart('text', ['text' => 'Hello']);
            $part->withField('empty', '');
            $part->withField('null', null);
            $part->withField('empty_array', []);
            $array = $part->toArray();
            expect($array)->toHaveKey('text');
            expect($array)->not->toHaveKey('empty');
            expect($array)->not->toHaveKey('null');
            expect($array)->not->toHaveKey('empty_array');
        });

        it('excludes private fields from array', function () {
            $part = new ContentPart('text', ['text' => 'Hello']);
            $part->withField('_private', 'secret');
            $array = $part->toArray();
            expect($array)->toHaveKey('text');
            expect($array)->not->toHaveKey('_private');
        });

        it('converts to string', function () {
            $part = ContentPart::text('Hello world');
            expect($part->toString())->toBe('Hello world');
        });

        it('returns empty string for non-text parts', function () {
            $part = ContentPart::imageUrl('https://example.com/image.jpg');
            expect($part->toString())->toBe('');
        });
    });

    describe('cloning', function () {
        it('creates deep clone', function () {
            $part = ContentPart::text('Hello');
            $clone = $part->clone();
            expect($clone)->not->toBe($part);
            expect($clone->type())->toBe($part->type());
            expect($clone->fields())->toBe($part->fields());
        });

        it('clones with independent fields', function () {
            $part = ContentPart::text('Hello');
            $clone = $part->clone();
            $clone = $clone->withField('new_field', 'value');
            expect($part->has('new_field'))->toBeFalse();
            expect($clone->has('new_field'))->toBeTrue();
        });
    });

    describe('complex field handling', function () {
        it('handles array fields', function () {
            $part = new ContentPart('custom', ['data' => ['key' => 'value']]);
            expect($part->get('data'))->toBe(['key' => 'value']);
        });

        it('handles object fields', function () {
            $object = (object) ['key' => 'value'];
            $part = new ContentPart('custom', ['object' => $object]);
            expect($part->get('object'))->toBe($object);
        });

        it('correctly identifies simple part with only text field', function () {
            $part = new ContentPart('text', ['text' => 'Hello']);
            expect($part->isSimple())->toBeTrue();
            expect(count($part->fields()))->toBe(1);
        });

        it('correctly identifies non-simple part with multiple fields', function () {
            $part = new ContentPart('text', ['text' => 'Hello', 'metadata' => 'extra']);
            expect($part->isSimple())->toBeFalse();
            expect(count($part->fields()))->toBe(2);
        });
    });
});